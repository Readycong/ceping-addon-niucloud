# 服务器端图片/视频去水印服务 - 实施计划

> 说明：所有 Python 代码位于 `python/wm-service/`；PHP 改动位于 `niucloud/addon/ai_wenxiu/`；小程序改动以 `uni-app/src/addon/ai_wenxiu/` 为开发源，完成后同步覆盖 `niucloud/addon/ai_wenxiu/uni-app/` 分发副本。不新增数据表。

## Task 1: Python 服务骨架（配置/日志/HTTP API/启动脚本/venv）

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: None

* **Description**:

  * 建立 `python/wm-service/` 目录：`requirements.txt`、`config.example.ini`、`app/`（config、logging、http\_server、worker、queue、repository、resolver、processor、ws）、`scripts/`、`logs/`、`tmp/`（后两者运行期生成，.gitkeep 忽略内容）。

  * settings 加载 config.ini（ini + 环境变量覆盖）：HTTP host/port/token、worker 并发（图片/视频分别限流）、MySQL、Redis、upload\_root（默认指向 `../../niucloud/public/upload`）、ffmpeg/ffprobe 路径、任务/下载限额、边缘带比例、重试与租约时间、日志级别。

  * 日志：按日 RotatingFileHandler + 控制台，结构化字段。

  * FastAPI 应用：`GET /health`（无鉴权：redis ping、mysql select1、ffmpeg -version、队列深度、processing 租约数、版本号）、`GET /metrics`（Bearer）、`POST /internal/tasks`（Bearer，body 校验 task\_id 存在且为 watermark 在途任务，等价投递的降级通道）。

  * `run.py` 单进程内启动 uvicorn Server（后台线程）+ WorkerPool（主线程），统一信号/键盘中断优雅退出（等待子进程结束、释放租约）。

  * 脚本：`scripts/setup_venv.bat`（建 .venv + pip install）、`scripts/run.bat`（Windows 前台运行）、`scripts/wm-service.sh`（Linux start/stop/restart/status，pid 文件 + nohup 日志，供守护）；提供 `config.example.ini` 与一份 supervisor/systemd 片段示例（conf 文件）。

* **Acceptance Criteria Addressed**: AC-1, AC-12, NFR-1, NFR-4, NFR-6

* **Test Requirements**:

  * `rule` TR-1.1: 干净 venv 中 `pip install -r requirements.txt` 成功；`python run.py` 启动后 `GET /health` 返回 200 且各依赖检查字段为 true/ok；错误/缺失 Bearer 访问 /metrics 返回 401/403。证据：命令输出与 curl 响应。

  * `rule` TR-1.2: 配置缺失（如 MySQL 段）时启动失败并给出明确错误；日志文件按日落盘。证据：日志文件内容。

  * `rubric` TR-1.3: 部署就绪度；scale 1-5；anchors 1=无法启动 3=可启动但配置需摸索 5=模板/脚本/说明齐备可独立启停；threshold >=4；证据：按脚本从零启动/停止/重启记录。

## Task 2: Redis 可靠队列 + MySQL 任务仓储 + WS 发布器

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: Task 1

* **Description**:

  * 队列常量 key：waiting list `aiwx:wm:jobs`；processing zset `aiwx:wm:processing`；恢复选主锁 `aiwx:wm:recover-lock`（SET NX EX）。载荷 JSON `{"task_id":int,"enqueue_ts":int}`。

  * enqueue（供 /internal/tasks 复用）：LPUSH waiting。

  * 消费循环：BRPOP（超时 5s 轮空保活）→ ZADD processing score=now+lease → 处理回调 → finally ZREM；认领前先查任务存在/类型/状态，终态或非 watermark 直接丢弃。

  * 恢复扫描（周期 60s，选主）：zset 中 score\<now 的成员重新 LPUSH 并从 zset 移除，任务表 retry\_count+1（载荷带 attempts）；超过最大重试（默认2）则把任务置失败 `WM_RETRY_EXHAUSTED`。

  * repository（pymysql，每操作短连接或带 ping 的连接池，dict cursor）：`get_task(task_id)`、`claim(task_id)`（UPDATE ... WHERE status=0 影响行判定）、`update_progress(task_id, p)`、`finalize_success(task_id, result, material_row, member_quota)`（事务：条件更新任务 + INSERT material（按 related\_task\_id+member 幂等查重）+ 非会员 wm\_quota upsert）、`finalize_fail(task_id, msg)`（WHERE status IN(0,1)）。

  * WS 发布器（redis-py）：完整复刻 PHP 信封与 buildPayload 字段（msg\_id 规则 `t{id}-{status}-{progress}-{md5(finish+err)前6}`、type/title 折叠空白、result 仅成功下发、consume/refund\_status/publish\_status）；publish 三事件，异常仅记日志不炸主链路。

* **Acceptance Criteria Addressed**: AC-1, AC-4, AC-9, NFR-1

* **Test Requirements**:

  * `rule` TR-2.1: LPUSH 一条不存在 task\_id 的消息，Worker 静默丢弃且无异常栈；发一条 DB 中 status=0 的 watermark 任务（用临时无处理器桩或真实处理器后续任务验证）能被 BRPOP 认领且 processing zset 出现成员、完成后移除。证据：Redis 数据观测与日志。

  * `rule` TR-2.2: WS 发布器产出的 JSON 与同任务 PHP 端 buildPayload 字段集一致（写一个对比脚本/逐字段核对）；终态 UPDATE 重复执行不产生第二条 material、不重复加 quota。证据：脚本输出 + DB 计数。

  * `rule` TR-2.3: 手工 ZADD 一条过期租约后，恢复扫描在一个周期内将其重入 waiting 并使 attempts 递增；超限后任务 status=3/error 含重试耗尽。证据：Redis/DB 变化记录。

## Task 3: PHP 提交与任务列表接口（配额/在途唯一/投递）

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: None

* **Description**:

  * `AiwenxiuDict` 增加 `TASK_WATERMARK='watermark'`。

  * 新增 `app/service/core/wm/CoreWmTaskService.php`（或 api/wm 下）：`submit(array $in)`：配额校验（复用 `CoreVipService::getWmStatus`，closed/remain 拒绝，错误码 WM\_CLOSED/WM\_QUOTA）；在途唯一（`type=watermark AND member AND status IN(0,1)` → WM\_TASK\_RUNNING）；链接模式提取 URL + 平台识别（抖音/小红书/直链白名单，其余 WM\_PLATFORM\_UNSUPPORTED，不建任务）；上传模式经 UploadSupport 落盘并记录绝对路径、相对 url、大小、媒体类型。

  * 建任务：type=watermark、title（去水印·{平台|本地素材}·{视频|图片}）、params（mode,media\_type,link,platform\_code,file\_path,file\_url,file\_size,submit\_meta）、progress=0、timeout（图 300/视频 1800 秒）。

  * 投递：phpredis LPUSH `aiwx:wm:jobs`；失败（无扩展/Redis 异常）把任务置失败（error=WM\_SERVICE\_UNAVAILABLE）并抛同码，不扣次。

  * `lists()`：本人 watermark 任务近 50 条，格式化 task\_id/title/status/status\_text/progress/error\_msg/time/finish\_time/result（url,cover,thumb\_url,duration,watermark\_detected,material\_id,platform,mode,media\_type）。

  * 控制器 `Watermark.php`：保留 quota；新增 `task()`（POST，兼容 JSON 链接与 multipart 上传同一入口：有 $\_FILES 即上传模式）、`tasks()`（GET）；删除 parse/save mock。

  * 路由：`POST watermark/task`、`GET watermark/tasks`，移除 parse/save。

  * 上传文件 PHP 侧不做永久归属：源文件保留（作为处理输入），产物单独写 wm 目录。

* **Acceptance Criteria Addressed**: AC-2, AC-5, AC-7, AC-10

* **Test Requirements**:

  * `rule` TR-3.1: 三类身份（会员/有剩余非会员/次数用尽非会员）提交结果分别为成功/成功/WM\_QUOTA；功能关闭返回 WM\_CLOSED；成功仅建任务与 LPUSH，不增加 wm\_quota（计数在成功后）。证据：接口响应 + DB/Redis 对比。

  * `rule` TR-3.2: 在途存在 status∈(0,1) watermark 任务时提交返回 WM\_TASK\_RUNNING，任务表行数与队列长度不变。证据：接口响应 + 计数。

  * `rule` TR-3.3: 不支持平台（如视频号链接/非媒体 URL）在提交期返回 WM\_PLATFORM\_UNSUPPORTED 且无任务行；非白名单后缀上传返回文件错误；停掉 Redis 后提交返回 WM\_SERVICE\_UNAVAILABLE 且任务被置失败。证据：接口响应与 DB。

  * `rule` TR-3.4: `GET watermark/tasks` 返回字段齐全、时间为 unix 秒、按 task\_id 倒序、仅本人可见。证据：接口响应。

## Task 4: 自研链接解析器与下载器

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: Task 1

* **Description**:

  * `app/resolver/`：`extract_url(text)`；平台 detector（douyin/xhs/direct）。

  * httpx 客户端：桌面浏览器 UA 池、gzip、max\_redirects=8、连接/读取超时、cookie 透传；SSRF 校验（解析 IP 拒绝私网/保留地址，仅 http/https）。

  * 抖音解析器：短链跟随 → share/video/{id} 页 → 提取 `_ROUTER_DATA`/RENDER\_DATA JSON → 视频 play\_addr（playwm→play 变换、URL 反转义）或图集；产出 {type, title, author, cover, items:\[{kind,url}], platform}。

  * 小红书解析器：xhslink 跟随 → explore/discovery/item 页 → `__INITIAL_STATE__` JSON（处理 undefined 占位）→ 视频 h264/h265 masterUrl 或图集图片；同样产出标准结构。

  * 直链兜底：HEAD/GET 检查 Content-Type 与后缀。

  * 下载器：流式下载到临时文件，视频 500MB/图片 20MB 限额、超时；返回本地路径与探测到的真实媒体类型（魔数嗅探，不信任后缀）。

  * 错误码：WM\_PLATFORM\_UNSUPPORTED、WM\_LINK\_RESOLVE\_FAILED、WM\_DOWNLOAD\_FAILED、WM\_MEDIA\_TOO\_LARGE、WM\_MEDIA\_INVALID。

  * 解析器纯函数尽量可单测：用保存的样例 HTML  fixture 测试 JSON 提取（不依赖实时网络）。

* **Acceptance Criteria Addressed**: AC-8, AC-10, NFR-7

* **Test Requirements**:

  * `rule` TR-4.1: 提取/平台识别/SSRF/直链 Content-Type 分支有可运行单测（pytest 或脚本断言），全部通过；内网 URL、超限下载被拒。证据：测试输出。

  * `rule` TR-4.2: 抖音/小红书解析函数可用本地 HTML fixture 正确抽出媒体 URL 与标题；解析失败路径返回明确错误码而非异常逃逸。证据：fixture 测试输出。

  * `rubric` TR-4.3: 真实链接有效性；scale 1-5；anchors 1=全失败 3=直链+其一平台可用且失败分支明确 5=双平台视频/图集均可用；threshold >=3；证据：真实链接试跑记录（受反爬影响时记录失败原因与分支完整性）。

## Task 5: 图片去水印处理流水线

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: Task 1

* **Description**:

  * `app/processor/image.py`：imdecode 读取（兼容 unicode 路径，numpy fromfile）；边缘带 mask（四边比例可配，默认 12%）：灰度/HSV 自适应阈值 + 形态学开闭运算 + 连通域面积/长宽过滤 + 膨胀；mask 面积占比超阈值（默认 15%）判定未检测到，回退原样复制。

  * `cv2.inpaint`（TELEA 默认/NS 可选，radius 可配）；按原类型编码输出（jpg 质量可配，png 保留），imencode + tofile 写盘。

  * 输出 {ok, output\_path, rel\_url, width, height, file\_size, watermark\_detected}；进度回调（检测 40%、修复 80%、编码 95%）。

  * 准备 2\~3 张带角标水印的测试样图（可在处理过程中用代码给样图加角标生成标准输入，保证可重复验证）。

* **Acceptance Criteria Addressed**: AC-2, AC-11

* **Test Requirements**:

  * `rule` TR-5.1: 对「人工加固定角标水印」的标准样图处理成功，输出文件存在、尺寸可读、格式正确、rel\_url 位于 upload/ai\_wenxiu/wm/image/ 下；无水印样图走原样复制分支且 watermark\_detected=false。证据：文件检查与输出 JSON。

  * `rubric` TR-5.2: 去水印观感；scale 1-5；anchors 1=水印仍清晰/画面崩坏 3=角标不可见但有可察觉涂抹 5=自然融合；threshold >=3；证据：处理前后对比图。

## Task 6: 视频去水印处理流水线

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: Task 1

* **Description**:

  * `app/processor/video.py`：ffprobe（json 输出）取 width/height/duration/fps/旋转/音频流索引；超时长/大小拒绝。

  * 抽帧：ffmpeg 均匀取 N 帧原始 rgb；边缘带持久性检测（对每帧边缘候选框做跨帧出现率投票，过滤画面移动内容），候选框做位置聚类并沿时间轴合并为片段 \[{t0,t1,x,y,w,h}]（支持四角跳动水印）；无候选→直接流复制 `-c copy`（不重编码）。

  * 渲染方案：按片段 trim + delogo 分段输出临时分片（视频 libx264 -crf/-preset/-threads 可配，音频 aac 或 -c:a copy），concat demuxer 合并；或 filter\_complex 单图方案（择稳者实现，要求音画同步）。

  * `-progress pipe:1 -nostats` 解析 out\_time\_ms 驱动 55\~95% 百分比；asyncio.subprocess/ subprocess 带任务级超时强杀（Windows taskkill / POSIX kill 进程组）。

  * 封面：成功后取第 1s 帧 jpg 作为 thumb。

  * 标准样片：用 FFmpeg 给一段无水印短片在四角按时间段叠加 logo 生成可重复输入。

* **Acceptance Criteria Addressed**: AC-3, AC-11

* **Test Requirements**:

  * `rule` TR-6.1: 标准样片（含四角分时段 logo）处理成功；ffprobe 验证成片时长与源一致（±0.3s）、音频流存在；无水印样片走流复制且输出可播放。证据：ffprobe 输出与文件检查。

  * `rule` TR-6.2: 处理过程可观察到 ≥3 次进度回调节流输出且单调不减；超时配置极短时子进程被强杀、任务走失败路径且临时文件清理。证据：进度日志与故障演示。

  * `rubric` TR-6.3: 去水印观感（角标不可见、无明显闪烁、音画同步）；scale 1-5；anchors 同 AC-11；threshold >=3；证据：成片抽帧前后对比与播放检查。

## Task 7: Worker 编排：端到端任务执行（认领→解析/读取→处理→落盘→终态→WS）

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: Task 2, Task 4, Task 5, Task 6

* **Description**:

  * `app/worker.py` 编排：取任务（含 params 解析）→ claim → 按 mode：link 走 resolver+downloader；upload 直接读 params.file\_path（存在性/大小/越界检查，防穿越）→ 魔数判定 image/video 分派处理器 → 产物写入 `upload_root/ai_wenxiu/wm/{image|video}/{Ym}/{d}/`（文件名 task\_id+随机串）→ finalize\_success（任务 result 含 url/cover/duration/宽高/size/watermark\_detected/material\_id/platform；material 行；quota 计数）→ WS 帧序列（claim 10%、阶段帧、终态帧）。

  * 失败路径：任何阶段异常 → finalize\_fail（错误码/消息截断 500 字）→ task\_failed 帧；不计数、不写资产；临时文件清理；源上传文件保留。

  * 视频/图片信号量分别限流；视频处理期间定期续租 zset 租约（每 60s ZADD 刷新 score）。

  * 优雅停机：收到停止信号后等待当前任务完成或到宽限期，新 BRPOP 停止。

* **Acceptance Criteria Addressed**: AC-2, AC-3, AC-4, AC-7, AC-9

* **Test Requirements**:

  * `rule` TR-7.1: 端到端（PHP 投递上传图片/视频任务）：任务 0→1→2、WS 帧完整、文件/material/quota 一致；人为制造处理失败（损坏文件）时 0/1→3、task\_failed 帧、quota 不增、无 material、临时目录无残留。证据：DB/Redis/文件/WS 观测记录。

  * `rule` TR-7.2: kill 服务进程模拟崩溃，重启后租约任务被重接管，最终成功或重试耗尽失败；material/quota 不重复。证据：故障注入记录。

  * `rule` TR-7.3: params.file\_path 指向 upload\_root 之外或不存在时任务失败且不读取越界文件。证据：日志与任务状态。

## Task 8: 小程序去水印页面任务化改造

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: Task 3

* **Description**:

  * `api/watermark.ts`：保留 getWmQuota；新增 `createWmTask(link)`（JSON POST）、`uploadWmTask(filePath, fileType)`（multipart：mode/media\_type/file）、`getWmTasks()`；删除 parse/save。

  * `pages/watermark/index.vue` 重写：配额徽章/分段保留；提交后 toast「任务已提交」并刷新任务列表（不跳页）；存在在途任务时两个提交按钮置灰显示「处理中… x%」。

  * 功能区下方「我的去水印」区块：列表卡片（图片/视频图标、标题、平台 pill、提交时间 fmtTime、状态文案：排队中/去水印中+进度条/已完成/失败+原因、完成时间）；onLoad 拉取；本页 onShow 注册 `watchAllTasks` 过滤 `msg.type==='watermark'` 更新本地项（进度/状态），终态后静默重拉列表；onHide/onUnload 解绑；成功卡片显示缩略图，点击图片 previewImage、视频 previewMedia，并标注「已存入我的资产」；失败后放开提交。

  * `utils/aiwx.ts` 的 TASK\_TYPE\_NAME 兜底增加 `watermark: '去水印'`（全局终态 toast 直接生效）。

  * 同步改动到 `niucloud/addon/ai_wenxiu/uni-app/` 分发副本（api/utils/page 三处）。

  * 错误码文案：WM\_QUOTA/WM\_CLOSED/WM\_TASK\_RUNNING/WM\_PLATFORM\_UNSUPPORTED/WM\_SERVICE\_UNAVAILABLE 等统一中文提示。

* **Acceptance Criteria Addressed**: AC-4, AC-5, AC-6

* **Test Requirements**:

  * `rule` TR-8.1: mp-weixin 构建通过（npx uni build -p mp-weixin / publish 流程），页面无 TS/模板错误；两份副本内容一致。证据：构建日志与文件 diff。

  * `rule` TR-8.2: 开发者工具（清缓存后）实测：提交后列表即时出现排队项；WS 推进度条与状态无需手动刷新；终态出现产物与「已存入资产」；处理中再次提交按钮不可点；toast 标题含「去水印」。证据：automator/截图/录屏与 WS 日志。

  * `rule` TR-8.3: 失败任务显示失败原因并可再次提交；离开页面无残留 WS handler（日志中 watchAll 注销）。证据：页面日志。

## Task 9: 端到端联调与全量验证

* **Status**: `pending`

* **Priority**: `high`

* **Depends On**: Task 7, Task 8

* **Description**:

  * 本机启动四常驻：MySQL/Redis（已在）、`queue:listen`、`aiwenxiu:websocket`、Python wm-service；PHP 走 .env 现有配置。

  * 全量回归 AC：图片上传、视频上传（含跳动 logo 样片）、直链任务、真实抖音/小红书链接试跑（记录外部风险）、视频号被拒、配额三种身份、在途唯一、崩溃恢复、health/鉴权、产物在资产页可见、首页 generating 计数 ±1。

  * mp-weixin 重新构建并清开发者工具 compile 缓存后回归。

  * 清理临时诊断脚本/测试文件（保留正式单测/fixture）。

* **Acceptance Criteria Addressed**: AC-1\~AC-12

* **Test Requirements**:

  * `rule` TR-9.1: 全部规则类 AC（AC-1\~AC-7、AC-9、AC-10）有实测证据并记录在任务 Completion Evidence。证据：证据清单。

  * `rubric` TR-9.2: AC-8/AC-11/AC-12 三项 rubric 按锚点评分且达标（>=3 / >=3 / >=4）。证据：评分理由与素材。

## Task 10: 部署交付物与进程守护说明交付

* **Status**: `pending`

* **Priority**: `medium`

* **Depends On**: Task 9

* **Description**:

  * 复核 config 项全部可配且 example 完整；日志/tmp 目录策略（自动创建、tmp 启动清孤儿）；wm-service.sh 的 start/stop/restart/status 在类 Linux 语法自检；Windows run.bat 可用。

  * 交付时向用户输出：venv 初始化、配置填写、启动命令、与 queue:listen/aiwenxiu:websocket 一并纳入守护的具体方式（Windows 可 NSSM/计划任务，Linux supervisor/systemd 片段由仓库示例提供）、生产 Nginx 无需额外开放端口（服务仅回环/内网）的说明。

* **Acceptance Criteria Addressed**: AC-12, NFR-5, NFR-6

* **Test Requirements**:

  * `rule` TR-10.1: 按交付步骤在当前机器完成一次 stop→start→status 循环，health 恢复绿色；示例配置与脚本中无真实密码。证据：命令记录。

  * `rubric` TR-10.2: 同 TR-1.3（部署就绪度复评）；threshold >=4；证据：独立复走记录。

