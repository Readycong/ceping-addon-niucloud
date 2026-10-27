# 服务器端图片/视频去水印服务（Python）与小程序任务化接入 - 产品需求文档

## Overview
- **Summary**: 在项目内新建一个生产级、可水平扩展的 Python 去水印服务（HTTP API + 多 Worker 进程，Redis 可靠队列），在服务器本地使用 OpenCV/FFmpeg 完成图片与视频的水印检测与去除；PHP 主应用只负责建任务、配额与在途校验、投递；任务进度复用既有 WebSocket 通道实时反馈，成功产物自动进入「我的资产」。
- **Purpose**: 替换现有去水印 sandbox mock（`WmService::parseLink` 全部为模拟数据，未做任何真实解析/去除），让小程序「一键去水印」具备真实可用的端到端能力，且不依赖任何第三方去水印/解析付费服务。
- **Target Users**: 纹绣老板 AI 小程序 C 端会员/非会员用户（门店经营者），以及负责部署运维的服务端运维人员。

## Goals
- 通用图片去水印：本地上传图片，服务端自动检测边缘水印并修复输出。
- 通用视频去水印：本地上传视频，服务端自动检测角标/跳动水印位置并按时间段去除，保留原声。
- 链接去水印：抖音、小红书分享链接由服务端自研解析器取得无水印源媒体后处理；直链媒体（mp4/mov/jpg/png 等）通吃。
- 任务异步化：提交即返回任务，排队/处理中/成功/失败与百分比进度经现有 WebSocket 实时同步；去水印任务计入首页「AI 生成中」。
- 高可用：Redis 可靠队列（多 Worker 竞争消费、崩溃租约恢复、有限重试、超时兜底）、HTTP 健康检查、无状态可水平扩展、临时文件与日志可运维。
- 强约束：同一用户同一时间只允许一个处理中的去水印任务；配额按既有规则校验（成功才计数）。

## Non-Goals
- 用户手动框选水印区域（v1 为全自动；检测不到水印时原样输出并在结果中标注）。
- 微信视频号（channels）链接解析（依赖微信客户端协议，HTTP 自研不可用，提交返回明确的「暂不支持」失败原因）。
- 任意第三方去水印 SaaS / 云厂商视觉 API / 第三方解析接口（运行期不得外联此类服务）。
- 画面中央大面积贴纸、满屏弹幕、动态特效遮挡的完美还原（自动算法仅保证边缘/角标类水印的有效处理）。
- 后台管理端新增去水印专属页面（统一任务表数据在后台「全部任务」中自然可见）。
- 一次提交多个任务、历史任务重新处理。

## Background & Context
- 现有去水印为 mock：[WmService.php](file:///d:/huichuang-Projects/ai-worker/niucloud/addon/ai_wenxiu/app/service/api/wm/WmService.php) 的 `parseLink/parseUpload` 生成假元数据，`mockVideo()` 仅返回一张占位图；控制器 [Watermark.php](file:///d:/huichuang-Projects/ai-worker/niucloud/addon/ai_wenxiu/app/api/controller/ai_wenxiu/Watermark.php) 暴露 quota/parse/save 三个接口。
- 统一任务表 `aw_ai_wenxiu_task`（前缀 `aw_`），状态机 0 排队 / 1 处理中 / 2 成功 / 3 失败，含 `progress`、`params(json)`、`result(json)`、`timeout_at`、`vendor_task_id` 等字段；状态流转与 WS 推送集中在 [CoreTaskService.php](file:///d:/huichuang-Projects/ai-worker/niucloud/addon/ai_wenxiu/app/service/core/task/CoreTaskService.php) 与 [CoreTaskPushService.php](file:///d:/huichuang-Projects/ai-worker/niucloud/addon/ai_wenxiu/app/service/core/websocket/CoreTaskPushService.php)。
- WS 通道：业务侧 `PUBLISH aiwx:ws:m:{site_id}:{member_id}`，消息信封 `{"type":"task_progress|task_finished|task_failed","data":<payload>,"ts":unix}`，载荷字段由 `CoreTaskPushService::buildPayload()` 定义；Workerman 进程（`php think aiwenxiu:websocket`，8282）订阅转发，连接/重连时自动推送在途快照（快照读任务表）。
- 队列：`.env [QUEUE] state=true`，PHP 侧 `queue:listen`（Workerman RedisQueue，key 前缀 `md5(root_path())`）消费 PHP Job；Python 不复用该格式，使用独立的简单可靠队列 key（见 FR-1），避免双方协议耦合。
- 资产表 `aw_ai_wenxiu_material`：`source` 已预留 `wm_link/wm_upload` 枚举，含 `wm_id/wm_author/wm_title/related_task_id/source_platform` 等字段；文件物理目录 `niucloud/public/upload/ai_wenxiu/...`，DB 存相对路径如 `upload/ai_wenxiu/image/202610/07/xxx.png`，前端 `img()` 拼 `VITE_IMG_DOMAIN`。
- 配额表 `aw_ai_wenxiu_wm_quota`（site+member+date 唯一，count 当日成功次数）；`CoreVipService::getWmStatus()` 返回 vip/used/free_daily/remain/closed；会员不限次。
- 首页待办漏斗 `TaskQueryService::todoFunnel()` 的 `generating` 已统计该用户全部 `status in (0,1)` 任务（不区分 type），新类型任务入库即自动参与计数。
- 小程序前端 WS 单例 [websocket.ts](file:///d:/huichuang-Projects/ai-worker/uni-app/src/addon/ai_wenxiu/utils/websocket.ts) 提供 `watchAllTasks(cb)`；[aiwx.ts](file:///d:/huichuang-Projects/ai-worker/uni-app/src/addon/ai_wenxiu/utils/aiwx.ts) 提供终态 toast、首页数据防抖同步。
- 前端规范：uni-app 以 `uni-app/src/addon/ai_wenxiu/` 为开发源，构建脚本同步产物；插件分发副本 `niucloud/addon/ai_wenxiu/uni-app/` 需同步覆盖。
- 运行环境（本机）：Python 3.13.9（Anaconda）、OpenCV 4.11、NumPy 2.3、httpx 0.28、Pillow 12、FFmpeg N-120025（20250624，含 ffprobe）均可用；缺 fastapi/uvicorn/redis-py/pymysql，需在独立 venv 安装。
- MySQL：127.0.0.1:3357，库 aiworker，用户 root；Redis：127.0.0.1:6379，无密码，db0。

## Functional Requirements

### Python 去水印服务（`python/wm-service/`）
- **FR-1 服务骨架与可靠队列**
  - 独立 venv（`.venv`）与 `requirements.txt`（fastapi、uvicorn、pymysql、redis、httpx、opencv-python-headless、numpy、pillow；pydantic 随 fastapi）；配置经 `config.ini`/环境变量（MySQL、Redis、上传根目录、ffmpeg 路径、并发数、限额），提供 `config.example.ini`，密钥不入库。
  - 一个命令同时启动：HTTP API（uvicorn，监听内网地址，共享 Bearer 令牌）+ N 个 Worker 线程（N 可配，图片/视频可分别限流，视频默认并发 1~2 防 CPU 打满）。
  - HTTP：`GET /health`（无鉴权，返回 Redis/MySQL/ffmpeg/队列深度/本 worker 存活）、`GET /metrics`（Bearer，处理量/失败量/在途/耗时分位）、`POST /internal/tasks`（Bearer，PHP 投递的备用通道，成功提交等价于 LPUSH，用于 Redis 直连失败降级）。
  - 可靠队列：Redis List `aiwx:wm:jobs`（waiting）+ ZSet `aiwx:wm:processing`（member=任务载荷，score=租约截止 unix 时间戳）；Worker `BRPOP` 取任务→`ZADD` 占租约→处理→成功/失败均 `ZREM`；周期扫描（SET NX 选主）将超租约（默认 30 分钟，可配）任务重新入队，`retry_count` 超过 2（可配）置失败；任务载荷 `{"task_id":123,"enqueue_ts":...}`，任务详情一律以数据库为准。
  - 幂等：任务状态非 0/1 时 Worker 直接 ACK 跳过；所有终态更新走条件 UPDATE（`status IN (0,1)`）。
  - 日志：按日滚动文件日志（提交、解析、下载、检测、渲染、终态、耗时、异常堆栈），级别可配。
- **FR-2 链接解析器（自研，无第三方）**
  - 从粘贴文本中正则提取首个 http(s) URL；平台识别：抖音（douyin.com/iesdouyin.com 等域）、小红书（xhslink.com/xiaohongshu.com）。
  - 抖音：短链跟随重定向（浏览器 UA、httpx、超时与跳转上限）→ 取分享页视频/图集 ID → 提取页面 JSON 中的源地址，视频经 playwm→play 等已知无水印变换取得 mp4；图集下载全部图片。
  - 小红书：xhslink 跟随重定向 → explore 页 `__INITIAL_STATE__` JSON → 视频流地址或图集图片地址。
  - 直链兜底：Content-Type 为 video/* image/* 或 URL 后缀为媒体后缀时直接下载。
  - 不支持平台/解析失败：返回明确错误码（`WM_PLATFORM_UNSUPPORTED` / `WM_LINK_RESOLVE_FAILED`），任务置失败；视频号识别后直接给出「暂不支持视频号」。
  - 下载限额：视频默认 ≤ 500MB、10 分钟（可配），图片默认 ≤ 20MB；SSRF 基础防护（禁止内网 IP、仅 http/https）；临时文件落服务端工作目录，结束后清理。
- **FR-3 图片处理流水线**
  - OpenCV 解码（cv2.imdecode 兼容中文路径）；在可配置边缘带（默认四边各 12%）内做持久性高对比/高饱和区域检测，形态学连通域过滤小噪点，生成 mask 并膨胀。
  - `cv2.inpaint`（TELEA/NS 可配，默认 TELEA）修复；输出到 `public/upload/ai_wenxiu/wm/image/{Ym}/{d}/{task_id}_{rand}.jpg`（jpg 质量可配；png 透明图保留 png）。
  - 检测不到水印：复制原图作为产物，结果标记 `watermark_detected=false`。
  - 产物元数据：宽高、字节数、文件类型。
- **FR-4 视频处理流水线**
  - ffprobe 读取宽高/时长/fps/旋转/音频流；超限（时长/大小）直接失败并给出原因。
  - 均匀抽帧（默认 16~32 帧），在边缘带检测持久性水印框；按位置聚类并沿时间轴归并为若干「定位置片段」（覆盖抖音类四角跳动水印）。
  - 渲染：每个片段用 FFmpeg `delogo`（x/y/w/h）裁剪+修复，concat 复用音频（音频 `-c:a copy`，无音频则不映射）；libx264（crf/threads/preset 可配）；经 `-progress pipe:1 -nostats` 解析 out_time 换算百分比。
  - 全程进度区间：排队 0、已认领 10、解析/下载 10~35、检测 35~55、渲染 55~95、收尾 95~100；进度回写任务表并 PUBLISH（节流，关键阶段必发、相邻进度差 ≥5% 才发）。
  - 检测不到水印：不重编码，直接复制源流（`-c copy`），结果标记 `watermark_detected=false`，避免无谓画质损失。
- **FR-5 任务状态机对接（Python→MySQL/Redis）**
  - 直接更新 `aw_ai_wenxiu_task`：认领时 `status=1, progress=10`（条件 `status=0`）；过程进度；成功 `status=2,progress=100,result=JSON,finish_time`；失败 `status=3,error_msg,progress=100,finish_time`（条件终态，防重复）。
  - 每次状态/进度变更后，按 PHP 同构信封向 `aiwx:ws:m:{site_id}:{member_id}` PUBLISH 对应事件（progress/finished/failed），载荷字段与 `CoreTaskPushService::buildPayload()` 完全一致（msg_id/task_id/type/title/status/progress/result/error_msg/consume/refund_status/publish_status）。
  - 成功时在同一事务内：写 `aw_ai_wenxiu_material` 资产行（type/cat=`ai_generated`/source=wm_link|wm_upload/tag=`去水印`/url/thumb_url/duration/source_platform/wm_*/related_task_id/create_time）+ 非会员 `aw_ai_wenxiu_wm_quota` 当日 count upsert +1（会员跳过；失败一律不计数），并把 `material_id` 放入 task.result。
- **FR-6 PHP 侧改造**
  - 新增去水印提交服务：鉴权配额（closed→`WM_CLOSED`、remain 不足→`WM_QUOTA`，会员放行）；在途单任务校验（本人 `type='watermark' AND status IN (0,1)` 存在即拒，错误码 `WM_TASK_RUNNING`）。
  - 上传模式：multipart 字段 `file`，复用 `UploadSupport` 落盘（限 mp4/mov/jpg/jpeg/png/png），得到相对 url 与绝对路径；链接模式：校验文本含 http(s) URL 且平台可识别（不支持平台在提交期即拒绝，不建任务）。
  - 建任务（type=`watermark`，title 如「去水印·抖音视频」，params 存 mode/type/link/平台/文件绝对路径与相对 url/文件大小/提交时间；图片超时 300s、视频 30 分钟），随后 Redis `LPUSH aiwx:wm:jobs`；投递失败则任务置失败并抛 `WM_SERVICE_UNAVAILABLE`（不扣次）。
  - 新增 `GET watermark/tasks`：返回本人去水印任务列表（默认近 50 条，按 task_id desc），字段含 task_id/type/title/status/status_text/progress/error_msg/提交时间/完成时间/result（url、cover、duration、watermark_detected、material_id、platform）。
  - 保留 `GET watermark/quota`；删除 mock 的 parse/save 业务实现与前端调用（路由同步替换为 `POST watermark/task`、`GET watermark/tasks`）。
  - 字典新增任务类型常量 `TASK_WATERMARK='watermark'`（feature 常量已存在）。
- **FR-7 小程序端改造**（[watermark/index.vue](file:///d:/huichuang-Projects/ai-worker/uni-app/src/addon/ai_wenxiu/pages/watermark/index.vue) 与 [watermark.ts](file:///d:/huichuang-Projects/ai-worker/uni-app/src/addon/ai_wenxiu/api/watermark.ts)）
  - 保留配额徽章与「链接解析 / 本地上传」分段；提交后直接创建任务（链接文本提交；上传先传文件），按钮在存在在途任务时禁用并显示「处理中… xx%」。
  - 功能区下方新增「我的去水印」任务列表：每条显示类型图标、标题/平台、提交时间（精确到分）、状态文案（排队中/去水印中 xx%/已完成/失败+原因）、进度条；进入页面拉取一次，之后由 `watchAllTasks` 过滤 `type==='watermark'` 实时更新（终态补拉列表拿产物信息），页面隐藏解绑本页监听。
  - 成功条目展示产物缩略图/可点击预览（图片预览、视频 previewMedia），并提示「已存入我的资产」；失败条目可重新提交（放开按钮）。
  - 终态 toast 复用全局 `bindTaskFinishToast`（兜底名补「去水印」）；首页「AI生成中」数字随既有 `bindTaskDataSync` 自动增减。

## Non-Functional Requirements
- **NFR-1 高可用**：Worker 无状态可多实例；单 Worker 崩溃后租约过期任务被其他/重启实例接管；终态幂等；Redis/MySQL 短暂不可用时提交明确报错、处理中任务重试不丢；HTTP health 可接入探活。
- **NFR-2 可靠性**：视频子进程超时强杀（默认任务级 30 分钟，可配）；临时目录任务结束清理、启动时清孤儿临时文件；磁盘写入失败任务失败不留半资产。
- **NFR-3 性能**：图片 1080p 级处理 P95 ≤ 15s（本机 CPU）；1080p 30s 视频 P95 ≤ 90s（libx264 veryfast 量级）；Worker 数与视频并发可配，默认不对主站 PHP/MySQL 造成可感知压力。
- **NFR-4 安全**：HTTP API 仅绑内网/回环并持 Bearer 令牌（默认 127.0.0.1:8300，可配）；解析下载防 SSRF、限大小、限后缀；上传文件白名单；路径拼接防穿越；敏感配置不进代码库（提供 example）。
- **NFR-5 可观测**：结构化滚动日志 + `/health` + `/metrics`；任务每个阶段可在日志与 WS 帧中对应。
- **NFR-6 可复现部署**：venv 一键建立（`python -m venv` + `pip install -r`）；提供启动脚本与常驻方式说明（与 queue:listen、aiwenxiu:websocket 一样纳入进程守护）；Windows 本机可直接 `python run.py` 运行。
- **NFR-7 无第三方运行依赖**：除 MySQL/Redis/FFmpeg/OpenCV 等基础设施与开源库外，不调用任何商业去水印/解析接口。

## Constraints
- **Technical**: Python 3.13（venv）；OpenCV(headless)+FFmpeg 本机处理；PHP 8/ThinkPHP 主应用不引入 Python 依赖，两侧仅经 MySQL 与 Redis 耦合；不新增数据表（复用 task/material/wm_quota）；仅新增 Redis 业务 key（`aiwx:wm:jobs`、`aiwx:wm:processing`、可选 `aiwx:wm:recover-lock`）。
- **Business**: 会员不限次、非会员每日免费次数由既有配置决定；成功才计数；同一用户在途任务唯一；不支持视频号链接。
- **Dependencies**: Redis、MySQL 可用；服务器安装 FFmpeg 与中文字体无关（不渲染文字）；小程序需重新编译并清开发者工具缓存；生产需将 Python 服务纳入进程守护。

## Assumptions
- 链接解析针对 2026-10 时点抖音/小红书公开分享页结构实现，平台反爬策略变化属外部风险；解析器按模块隔离，失败表现为任务明确失败（可后续更新解析规则），不影响上传链路。
- 边缘/角标类水印占去水印诉求多数；中央贴纸等不在效果承诺内，检测不到时原样输出、任务仍算成功完成（不扣次？——见 AC：原样输出同样产出资产并计数一次成功处理）。
- PHP 上传文件与 Python 服务在同一台机器（通过绝对路径访问 `public/upload`）；未来分离部署时可改为 HTTP/对象存储，配置中预留。
- 服务仅在 IPv4 回环/内网暴露，不直接承载公网流量。

## Acceptance Criteria

### AC-1: Python 服务可启动、可探活、依赖可复现
- **Type**: `rule`
- **Given**: 一台具备 Python 3.13 与 FFmpeg 的服务器
- **When**: 按说明创建 venv、安装 requirements、填写 config、执行启动命令
- **Then**: HTTP API 与 Worker 均启动；`GET /health` 返回 200 且含 redis/mysql/ffmpeg 可达状态与队列深度；缺令牌访问 `/metrics` 返回 401/403
- **Pass Condition**: health 全绿、鉴权生效；`requirements.txt` 干净安装无报错
- **Evidence**: 启动日志、curl/health 与 /metrics 响应、pip 安装输出

### AC-2: 提交链路端到端打通（上传图片）
- **Type**: `rule`
- **Given**: 有剩余配额的登录用户
- **When**: 小程序上传一张带角标水印的图片提交去水印
- **Then**: 立即返回 status=0 的 watermark 任务；Python Worker 认领并处理；产物落在 `public/upload/ai_wenxiu/wm/image/...`，任务 status=2、result.url 可访问，资产列表出现该图，非会员当日 wm_quota.count +1
- **Pass Condition**: DB 任务行、material 行、quota 计数、文件四处一致可查
- **Evidence**: 任务表记录、文件系统产物、material 记录、接口返回、前后对比图

### AC-3: 提交链路端到端打通（上传视频）
- **Type**: `rule`
- **Given**: 有剩余配额的登录用户与一段带角标水印（含位置跳动）的 mp4
- **When**: 提交视频去水印
- **Then**: 任务经 0/1/2 流转，进度由 10 向 95 递增后到 100；成片可播放、音画同步且原声音轨保留，水印区域被 delogo 处理；资产出现视频记录（含时长、封面）
- **Pass Condition**: ffprobe 显示成片含原音频流且时长一致；抽帧对比水印位置像素已被修复
- **Evidence**: 任务进度帧序列、ffprobe 输出、成片抽帧截图

### AC-4: WebSocket 进度实时反馈且计入「AI生成中」
- **Type**: `rule`
- **Given**: 小程序已登录并进入插件（WS 已连接）
- **When**: 任一去水印任务被认领、进度更新、终态
- **Then**: 小程序收到 task_progress/task_finished（或 task_failed）帧，帧字段与 PHP 既有任务帧同构；首页 todoFunnel.generating 在提交后 +1、终态后 -1；终态弹出全局「任务：…/生成状态：…」toast
- **Pass Condition**: 抓包/automator 日志看到帧事件；首页数字与 DB 在途数一致
- **Evidence**: WS 帧记录、automator 或 devtool 日志、首页计数变化

### AC-5: 在途任务唯一约束
- **Type**: `rule`
- **Given**: 用户已有一个 status∈(0,1) 的 watermark 任务
- **When**: 再次提交链接或上传任务
- **Then**: 服务端拒绝（错误码 WM_TASK_RUNNING），不建新任务、不投递、不扣次；前端提交按钮禁用并显示当前进度
- **Pass Condition**: DB 无新任务行、队列无新消息；前端无法发起第二次提交
- **Evidence**: 接口响应、任务表计数、前端截图/日志

### AC-6: 去水印任务列表展示时间/进度/状态并实时更新
- **Type**: `rule`
- **Given**: 用户历史上去水印任务若干
- **When**: 进入去水印页
- **Then**: 功能区下方展示任务列表（提交时间到分、平台、状态文案、百分比进度条、失败原因、完成产物入口）；处理中任务无需刷新页面，进度随 WS 实时变化，终态自动刷新为产物态
- **Pass Condition**: 字段与 DB 一致；处理中列表进度自动跳变；离开页面后解绑监听
- **Evidence**: 接口返回、页面截图/录屏、WS 监听绑定日志

### AC-7: 配额规则
- **Type**: `rule`
- **Given**: 非会员（free_daily>0 已用完）、功能关闭用户、会员三种身份
- **When**: 分别提交
- **Then**: 已用完→WM_QUOTA 拒绝；功能关闭→WM_CLOSED 拒绝；会员→放行且不读写 wm_quota；任务失败时非会员当日计数不增加
- **Pass Condition**: 三类身份响应与 wm_quota 表变化符合规则；成功后计数 +1
- **Evidence**: 接口响应与 quota 表前后对比

### AC-8: 链接模式（抖音/小红书/直链/不支持平台）
- **Type**: `rubric`
- **Dimension**: 自研链接解析器有效性
- **Scale**: 1-5
- **Anchors**: 1 = 全部链接解析失败或无法下载；3 = 直链可用、抖音/小红书其一可用，失败有明确错误码；5 = 抖音与小红书（视频+图集）及直链均可取得源媒体并完成去水印，视频号/不支持平台在提交期被明确拒绝
- **Pass Threshold**: >= 3（受平台反爬外部风险影响，以直链+至少一条平台链路可用、错误分支完备为准）
- **Evidence**: 各链接任务记录、下载产物、错误码分支；解析器单测/试跑输出

### AC-9: 高可用与故障恢复
- **Type**: `rule`
- **Given**: 队列中存在处理中任务
- **When**: kill -9 Worker 进程后重启；或在任务渲染中断开 Redis 后恢复
- **Then**: 租约到期后任务被重新认领并重试（retry_count 递增，上限内置失败）；终态不重复计数、不重复写资产（material.wm_id/related_task_id 唯一去重）；无僵尸任务长期停留 status=1（有超时/租约扫尾）
- **Pass Condition**: 故障注入后任务最终为成功或达到重试上限后的失败；资产不重复；quota 不重复计数
- **Evidence**: 故障注入操作记录、DB 状态变化、日志

### AC-10: 安全与自托管约束
- **Type**: `rule`
- **Given**: 服务运行中
- **When**: 检查网络外联、接口鉴权、内网 URL、恶意文件上传
- **Then**: 处理链路不调用任何第三方去水印/解析商业服务（仅目标平台公开页面/CDN 与基础设施）；内部接口无 Bearer 不可用；内网地址 URL 被拒；非白名单后缀上传被拒；输出文件不逃逸上传目录
- **Pass Condition**: 代码审查与实测各拦截分支通过
- **Evidence**: 代码/配置检查、拒绝用例响应

### AC-11: 去水印处理质量（图片/视频）
- **Type**: `rubric`
- **Dimension**: 边缘水印去除的观感质量
- **Scale**: 1-5
- **Anchors**: 1 = 水印仍清晰可见或画面明显崩坏；3 = 角标水印基本不可见、修复区有可察觉涂抹/模糊；5 = 角标/边缘水印不可见且修复区与背景自然融合、视频无明显闪烁、音画同步
- **Pass Threshold**: >= 3
- **Evidence**: 标准测试素材（自备带角标样图/样片）处理前后对比、视频抽帧

### AC-12: 部署与运维交付完整性
- **Type**: `rubric`
- **Dimension**: 第三方按交付物独立部署的难易度
- **Scale**: 1-5
- **Anchors**: 1 = 只有代码、无法启动；3 = 可按脚本启动但配置/守护需摸索；5 = venv、requirements、config 模板、启动/停止/重启脚本、日志/临时目录、常驻守护说明齐备，新增 Worker 实例只需改并发或复制进程
- **Pass Threshold**: >= 4
- **Evidence**: 按交付物在干净 shell 完成启动/停止/重启的记录

## Open Questions
- 无（三项关键边界已经用户确认：全自动检测；抖音+小红书；独立 venv + 多 Worker）。
