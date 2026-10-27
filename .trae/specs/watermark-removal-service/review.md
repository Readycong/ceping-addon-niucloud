# 去水印微服务（Python + PHP + 小程序）独立代码审查报告

- 审查日期：2026-10-07
- 审查人：独立代码审查（只读，未修改任何业务代码）
- 审查依据：`.trae/specs/watermark-removal-service/spec.md`（FR/AC）与 `tasks.md`（TR）

## 一、审查范围与方法

**审查范围（均为实际逐行阅读）：**

1. Python 服务 `d:\huichuang-Projects\ai-worker\python\wm-service\`：`run.py`、`requirements.txt`、`config.example.ini`、`.gitignore`、`app/` 下 config/db/http_app/queue/repository/worker/runner/publisher/metrics/errors/log 全部、`app/processor/{common,image,video}.py`、`app/resolver/{base,http,service,direct,douyin,xhs}.py`、`scripts/` 下 bat/sh/systemd/supervisor 四份。
2. PHP 侧：`niucloud/addon/ai_wenxiu/app/api/controller/ai_wenxiu/Watermark.php`、`app/service/api/wm/WmService.php`、`app/api/route/route.php`、`app/dict/AiwenxiuDict.php`、`app/model/{Task,WmQuota}.php`、`app/service/core/task/CoreTaskService.php`、`app/service/core/vip/CoreVipService.php`、`app/service/core/websocket/CoreTaskPushService.php`、`app/command/Websocket.php`、`app/service/api/support/UploadSupport.php`、`app/service/api/task/TaskQueryService.php`、`app/lang/zh-cn/api.php`、`sql/install.sql`（task/material/wm_quota/user_vip 表结构与索引）。
3. 小程序：`uni-app/src/addon/ai_wenxiu/pages/watermark/index.vue`、`api/watermark.ts`、`utils/websocket.ts`、`utils/aiwx.ts`，并与分发副本 `niucloud/addon/ai_wenxiu/uni-app/` 做文件级 hash 对比。
4. 数据库：复用 `aw_ai_wenxiu_task`（type='watermark'）、`aw_ai_wenxiu_material`、`aw_ai_wenxiu_wm_quota`，未新增表（符合 Constraints）；逐一核对索引/唯一键。

**方法：** 静态逐行审查 + 跨端字段/状态机对账 + 并发时序推演 + `python -m compileall` 语法校验（通过）+ 双副本 hash/diff 对比。未启动服务、未做线上请求与真实素材实测（相关运行时结论以代码事实表述并明确标注）。

**问题统计：Critical 0 / Major 6 / Minor 13 / Suggestion 8。**

---

## 二、问题清单

### Critical（阻断）

无。

### Major（重要，建议交付前修复或给出实测结论）

#### M1. 可靠队列存在消息永久丢失窗口，且全链路无 status=0/1 孤儿任务兜底，用户会被“在途唯一”永久锁死

- 位置：
  - `d:\huichuang-Projects\ai-worker\python\wm-service\app\worker.py:89-123`（先 `BRPOP`，再读 DB，最后才 `ZADD` 租约）
  - `d:\huichuang-Projects\ai-worker\python\wm-service\app\queue.py:58-66`（`blocking_pop` 与 `lease` 是两条独立命令）、`queue.py:85-100`（恢复先 `ZREM` 再交上层）
  - `d:\huichuang-Projects\ai-worker\python\wm-service\app\worker.py:189-207`（`pop_stale` 的 ZREM → `bump_retry` → `reset_to_wait` → `requeue` 四步非原子）
  - `d:\huichuang-Projects\ai-worker\python\wm-service\app\worker.py:145-154`（任何失败路径最终都无条件 `ack()` 删租约；`_safe_fail` 写库失败时见 `worker.py:165-172`）
  - PHP 侧 `d:\huichuang-Projects\ai-worker\niucloud\addon\ai_wenxiu\app\service\api\wm\WmService.php:76-84`（在途判定只认 DB status）
- 问题描述：`BRPOP` 成功即把消息从 waiting list 移除，而 processing zset 的租约成员要等一次 MySQL 读取之后才 `ZADD`，两步不原子。恢复路径先 `ZREM` 再做 DB 更新和 `LPUSH`，同样不原子。租约恢复只扫 zset，不扫数据库。
- 具体后果（任一窗口内进程被 kill -9/OOM/断电，或 MySQL/Redis 在特定瞬间不可用）：
  1. 消息已不在 waiting、也不在 processing → **任务永久停留 status=0**；恢复扫描永远发现不了它。
  2. `_safe_fail` 因 DB 故障未写成终态时，`finally` 仍把租约成员删掉 → 任务永久停留 status=1，同样无人回收。
  3. 恢复时 `ZREM` 已成功、`LPUSH` 未发出 → 同上。
  4. 该用户之后每次提交都被 `WmService::submit` 的在途检查拒绝（`WM_TASK_RUNNING`），**无任何自愈手段，只能人工改库**，直接违背 NFR-1“处理中任务重试不丢”和 AC-9。
- 修复建议：
  - 取任务改为原子搬运：Lua 脚本一次性 `RPOP waiting + ZADD processing`（或 `BRPOPLPUSH` 到 per-instance processing list 后再 ZADD），消除裸 `BRPOP` 窗口；
  - 恢复循环增加“DB 孤儿扫描”：定期查 `type='watermark' AND status IN(0,1) AND create_time/timeout_at < now-阈值` 且其任务载荷不在 waiting/processing 中的任务，重置入队或判失败（PHP 已写 `timeout_at`，但当前无任何消费方，见 `WmService.php:102`）；
  - `_safe_fail` 写库失败时不要 `ack`，保留租约交恢复器重试；恢复四步改为“先 LPUSH 再 ZREM”或 Lua 原子化。

#### M2. finalize_success 的“行锁”在 autocommit 下不生效，material 无唯一键兜底：并发/重影下重复写资产、重复扣配额；失败任务还可能被滞后 worker 记成功账

- 位置：
  - `d:\huichuang-Projects\ai-worker\python\wm-service\app\db.py:98`（连接配置 `"autocommit": True`）
  - `d:\huichuang-Projects\ai-worker\python\wm-service\app\repository.py:106-191`（`SELECT ... FOR UPDATE` 注释声称行锁；仅对 status==2 幂等；终态 UPDATE 在 `repository.py:177-189`）
  - 表结构 `d:\huichuang-Projects\ai-worker\niucloud\addon\ai_wenxiu\sql\install.sql:133-138`（material 仅有普通索引 `idx_related_task`，**无唯一键**；wm_quota 有 `uk_site_member_date` 见 `install.sql:290`）
- 问题描述：
  1. pymysql 在 `autocommit=True` 下每条语句独立事务、语句结束即放锁，`SELECT ... FOR UPDATE` 不会把锁持有到后续 INSERT/UPDATE，`repository.py:108` 的“行锁 + 已终态判定”注释与实际行为不符。
  2. material 表没有 `(site_id, member_id, related_task_id)` 唯一索引（install.sql 里 `wm_id` 注释写着“幂等保存”，但也没有唯一约束）。
  3. 幂等短路只判断 `status==TASK_SUCCESS`（`repository.py:114`），**没有拦截 status==3（失败）**。
- 具体后果（时序：worker A 租约过期但进程未死 → 恢复器重投 → worker B 执行）：
  - A、B 同时走到 finalize：两边都查到 status=1、都查不到旧 material → 插入 2 组 material 行、配额 upsert 各 +1（**重复资产、非会员重复扣次**），与 FR-5“同一事务内”“成功才计数一次”和 AC-9“资产不重复、quota 不重复计数”直接冲突。
  - B 先合法失败（status=3）后，僵尸 A 走成功分支：`status==3` 不被拦截，material 与配额照常写入，而终态 UPDATE `WHERE status IN (0,1)` 影响 0 行被静默忽略 → **一个失败任务名下出现成功资产且扣了免费次数**，违背“失败不计数/不写资产”。
  - 注：纯 kill -9（进程已死）场景因重投后看到 status=2 提前返回，尚安全；风险出现在“活僵尸 + 重投”这一 M1 允许发生的场景。
- 修复建议：
  - 仓储连接显式 `BEGIN ... COMMIT`（或该事务用 autocommit=False 的独立连接），让 `FOR UPDATE` 真正持锁；
  - material 增加唯一键 `uk_related_task(related_task_id, site_id, member_id)`（图集多行可加序号列或改用 `wm_id` 唯一键），以数据库约束做最终兜底；
  - finalize_success 开头对 `status NOT IN (0,1)` 一律拒绝写入（含 status=3），保证终态单向。

#### M3. PHP“同一用户在途唯一”是 check-then-insert，无事务锁/唯一键兜底，并发双发可同时建任务

- 位置：`d:\huichuang-Projects\ai-worker\niucloud\addon\ai_wenxiu\app\service\api\wm\WmService.php:76-98`；表结构 `d:\huichuang-Projects\ai-worker\niucloud\addon\ai_wenxiu\sql\install.sql:39-47`（task 表无任何在途唯一约束）
- 问题描述：先 `count()` status IN(0,1)，再 `CoreTaskService::create()`，两步之间无行锁、无唯一键、无 Redis SETNX 占位。前端按钮置灰和串行请求场景没问题，但接口可被直接并发调用（弱网重复点击、脚本）。
- 具体后果：两个请求同时 count 到 0 → 建出两条 watermark 任务、各 LPUSH 一条消息，违反 AC-5（“DB 无新任务行、队列无新消息”）；两个任务还会在 worker 侧各自占用处理资源，且把 M2 的并发窗口从“崩溃恢复”扩大到“正常双发”（两个任务 task_id 不同，M2 去重也挡不住）。
- 修复建议（任选其一）：
  - 提交入口加 Redis 占位：`SET aiwx:wm:slot:{site}:{member} <task_id> NX EX 1800`，终态/失败时 DEL；
  - 或在 task 表加“在途槽”生成列 + 唯一索引（仅 status IN(0,1) 时为 member 维度固定值）；
  - 最轻量：`Db::transaction` 内对 member 维度一行做 `SELECT ... FOR UPDATE` 后再 count（吞吐足够，本业务单人 QPS 极低）。

#### M4. 视频链路对旋转元数据的处理无任何读/写/校验：重编码可能丢失 rotate 信息，且 OpenCV 抽帧与 ffmpeg 坐标系可能不一致

- 位置：
  - `d:\huichuang-Projects\ai-worker\python\wm-service\app\processor\video.py:48-88`（probe 未读取 side_data_list/tags.rotate/`display_matrix`，FR-4 明确要求读“旋转”）
  - 分段重编码 `video.py:428-464` 与无水印复制回退重编码 `video.py:527-533` 全部带 `-noautorotate`，但没有任何 `-metadata`/display matrix 写回；文件头注释（video.py:4）声称“旋转元数据原样保留”，代码中无对应实现
  - 抽帧 `video.py:103-117` 用 `cv2.VideoCapture`（后端在部分平台会按元数据自动旋正帧），而坐标缩放 `video.py:346-356` 使用 ffprobe 的编码尺寸
- 问题描述：带 rotate=90/270 元数据的手机视频（iPhone 上传 .mov 很常见）经 x264 重编码后，display matrix 是否保留完全取决于 ffmpeg 版本的默认行为（通常不随重编码传递），代码未做任何保证；OpenCV 与 ffmpeg 对同一旋转标记的处理还可能不一致，使检测坐标发生宽高错位。
- 具体后果：竖屏视频成片可能横置；或检测框被缩放/错位到错误区域（delogo 打在错误位置）。spec FR-4 与 AC-3 对“音画/方向正确”的承诺可能在旋转素材上落空。无水印 `-c copy` 分段不受影响（元数据随流复制）。
- 修复建议：
  - probe 显式读取 `side_data_list[].rotation`（或 tags.rotate）；
  - 二选一并实测固定：要么全程默认 autorotate（帧已是正向），检测/渲染都基于正向尺寸并接受旋转已烘焙；要么 `-noautorotate` 时用 `-metadata:s:v rotate="90"`（或 `display_rotation` 输出选项）把旋转写回成片；
  - 用一条 rotate=90 的真机 .mov 作为 TR-6 标准素材（当前“标准样片”未提旋转），ffprobe 成片方向 + 抽帧位置双重验证。此项若实测证明当前 ffmpeg 版本会保留元数据，可凭证据降级。

#### M5. 插件分发副本缺少 `utils/websocket.ts`，watermark 页面引用了不存在的模块，addon 副本无法独立编译

- 位置：
  - 引用：`d:\huichuang-Projects\ai-worker\niucloud\addon\ai_wenxiu\uni-app\pages\watermark\index.vue:146-147`（`@/addon/ai_wenxiu/utils/websocket`）
  - 实际目录 `d:\huichuang-Projects\ai-worker\niucloud\addon\ai_wenxiu\uni-app\utils\` 下只有 `aiwx.ts`，**没有 websocket.ts**（全目录 glob 无该文件）
  - 主仓开发源 `d:\huichuang-Projects\ai-worker\uni-app\src\addon\ai_wenxiu\utils\websocket.ts` 存在且实现完整
- 问题描述：开发源与分发副本 hash 对比结果——`api/watermark.ts` 与 `pages/watermark/index.vue` 两副本完全一致，但 `utils/aiwx.ts` 两副本是两套不同实现（分发副本仍是旧的 listener 版，开发源已改为模块级 reactive store 版，diff 31 行），且分发副本整体缺失 websocket.ts。TR-8.1 要求“两份副本内容一致”。
- 具体后果：本仓库自身构建走 `uni-app/src` 不受影响（这也是“构建通过”的原因）；但任何第三方按 addon 包方式使用 `niucloud/addon/ai_wenxiu/uni-app/` 分发副本时，watermark 页面直接编译失败（模块不存在）。影响 AC-12 的独立交付承诺。
- 修复建议：按 tasks.md Task 8 把 `utils/websocket.ts` 同步到分发副本，并把 `utils/aiwx.ts` 全量对齐（或明确该副本的同步构建步骤并在交付时重跑一次全量同步 + 副本侧构建验证）。

#### M6. SSRF 防护覆盖了内网段与重定向逐跳校验，但存在 DNS rebinding（TOCTOU）缺口

- 位置：`d:\huichuang-Projects\ai-worker\python\wm-service\app\resolver\http.py:21-43`、`45-62`
- 问题描述：请求钩子对每次请求（含每次重定向）做 `getaddrinfo` 并拒绝 loopback/private/link-local/reserved/multicast/unspecified，基础防护到位（`169.254.0.0/16` 元数据地址被 is_link_local 拦截，IPv4-mapped IPv6、`127.1` 等变体经解析后也被拦）。但钩子内解析一次、httpx 建连时再解析一次，攻击者可控权威 DNS 做短 TTL rebinding，可让校验拿到公网 IP、实际连接拿到 127.0.0.1/内网 IP。
- 具体后果：用户提交的链接由服务端发起请求，且同机 Redis 无密码、内网可能存在管理面；rebinding 可绕过“禁止内网”策略向内网 GET 固定路径（危害受限于 GET、无自定义头/body，但元数据/未鉴权内网接口仍有风险）。spec FR-2 措辞为“SSRF 基础防护”，故定级 Major 而非 Critical。
- 修复建议：自定义 httpx transport/connector：钩子解析得到 IP 后，连接时钉住该 IP（SNI/Host 头保留原域名，https 需正确校验证书），使“校验的 IP == 实际连接的 IP”；并可选对解析结果做一次性缓存复用。

### Minor（一般）

1. **ffmpeg 失败时丢失真正的错误输出。** `d:\huichuang-Projects\ai-worker\python\wm-service\app\processor\common.py:83-90` 对 ffmpeg 把 stderr 接到 DEVNULL，配合 `-loglevel error`，失败时 `common.py:135-137` 拼出的 detail 只是 stdout 末尾的 progress 文本，运维拿不到报错原因。建议 stderr 用 PIPE 尾部收集（或 `-progress pipe:2` 分流）。
2. **/health 的 ffmpeg 探活只查 ffprobe，且不把 worker 存活纳入健康判定。** `d:\huichuang-Projects\ai-worker\python\wm-service\app\http_app.py:25-34`（只跑 ffprobe -version）、`http_app.py:76-88`（workers_alive=0 仍返回 200）。ffmpeg 缺失/全部 worker 线程异常时探活仍“全绿”。
3. **Bearer 令牌非常量时间比较。** `d:\huichuang-Projects\ai-worker\python\wm-service\app\http_app.py:52` 建议 `hmac.compare_digest`。空 token 时 fail-closed（拒绝所有），逻辑正确。
4. **强杀/停机可能留下孤儿 ffmpeg。** ffmpeg 以独立进程组启动（`common.py:91-93`），`wm-service.sh` 的 stop 只 kill 父进程（`scripts\wm-service.sh:27-30`）；`scripts\wm-service.supervisor.conf` 未设 `killasgroup/stopasgroup`。父进程被 SIGKILL 后 ffmpeg 继续跑到完。systemd 单元因默认 cgroup 收编不受影响。建议 supervisor 加 `stopasgroup=true`、`killasgroup=true`，sh 脚本停服时按进程组清理。
5. **透明 PNG 修复后 alpha 被拍平。** `d:\huichuang-Projects\ai-worker\python\wm-service\app\processor\image.py:109-138`：检测到水印走 BGR inpaint 后再 `cvtColor(BGR2BGRA)`，输出的 alpha 恒为 255，原图透明区域丢失（文件仍是 .png）。建议对 alpha 通道单独处理或仅对 RGB inpaint 后合回原 alpha。
6. **链接任务在途期间媒体类型未知，图标恒显视频。** `uni-app\src\addon\ai_wenxiu\pages\watermark\index.vue:92`（`t.media_type === 'image' ? 🖼️ : 🎬`），链接模式 PHP 入库 `media_type=''`（`WmService.php:169`），图集任务完成前显示视频图标。
7. **页面仅 onUnload 解绑 WS，未按 FR-7“页面隐藏解绑”处理。** `uni-app\src\addon\ai_wenxiu\pages\watermark\index.vue:215-224`。navigateTo 压栈隐藏期间本页监听仍在（功能无害、回前台数据更新，仅与需求措辞/其它页面约定不一致）；不存在重复绑定问题（onLoad 单次）。
8. **Python 错误码命名与 spec 不一致。** spec FR-2 为 `WM_LINK_RESOLVE_FAILED`，实现为 `WM_LINK_PARSE_FAILED`（`d:\huichuang-Projects\ai-worker\python\wm-service\app\errors.py:19`，resolver 全部 raise 该码）。终态 error_msg 直接落中文文案，用户侧无影响，仅跨端代码字典/排障口径不一致。
9. **配额自然日依赖服务器时区。** `d:\huichuang-Projects\ai-worker\python\wm-service\app\repository.py:172` 用 `date.today()`，PHP 侧为 PRC 时区（`Websocket.php:53` 可见显式设 PRC）。生产 Linux 若 TZ=UTC，跨午夜前后 PHP 判定与 Python 计数可能落到不同日期。建议配置时区或统一用 `Asia/Shanghai` 计算 date。
10. **图片无像素尺寸上限，且图片处理不受任务 deadline 中断。** `image.py:104-137` 直接解码全分辨率；超大图（手机 1 亿像素级）可造成内存尖峰/长耗时，任务总超时（`runner.py:55`）只作用于 ffmpeg 子进程。建议加最长边/总像素上限或显式降采样。
11. **未知平台链接提交期未拒绝，与 FR-6 措辞有偏差。** `WmService.php:154-162` 对任何非抖音/小红书 host 一律按 `direct` 放行，非媒体网页要等 worker 解析失败才产生一条失败任务（短暂占用在途名额）。视频号等在提交期拒绝已实现（`WmService.php:29`、`149-153`）。建议对直链先做后缀/HEAD 预判，或明确接受此设计。
12. **Redis 不可用导致的置失败不推 WS 失败帧。** `WmService.php:104-116` 直接 UPDATE status=3 后抛异常，未调 `CoreTaskPushService::publishByTaskId(..., EVENT_FAILED)`；接口调用方能收到异常 toast，但若响应丢失，列表要靠下次进入页面才看到失败。影响很小。
13. **/metrics 的 submitted 计数重复。** HTTP 投递在 `d:\huichuang-Projects\ai-worker\python\wm-service\app\http_app.py:112-113` +1，worker 真正取到消息时 `worker.py:101` 再 +1；重投/重试也计入 submitted。仅观测口径问题。

### Suggestion（建议）

1. **`/internal/tasks` 重复投递可放大为租约互踩。** 对已在 waiting 的任务再次 LPUSH 会产生两条相同载荷（`attempts` 也相同，见 `http_app.py:101-114` 与 `queue.py:27-31`）：一个消费者 claim 失败后 `ack()`（`worker.py:128-130,154`）会删掉另一个消费者正在使用的同名 zset 成员，此后 `renew(gt=True)` 不再生效，租约丢失。建议：claim 失败者不要 ZREM（只有成功租约并完成处理的人才能 ack），或 enqueue 前去重（zset/list 内不存在才 LPUSH）。
2. **音频统一重编码 AAC 而非 FR-4 所述 `-c:a copy`。** `video.py:441-460` 每段 `-c:a aac -b:a 128k`（tasks.md Task 6 允许“aac 或 copy”）。AC-3“原声音轨保留/时长一致”可满足，但会带来轻微音质损失和极小边界时延；有音频分段可考虑 `-c:a copy`（容器/时间戳允许时）。
3. **config.example.ini 含弱口令样例且默认 token 为固定占位。** `config.example.ini:9,22`（`change-me-please`、`12345678`）。建议口令样例明确写 `<your-strong-password>`；启动时若 token 仍为默认值可打 WARNING 日志。config.ini 已被 `.gitignore` 正确忽略（已核对）。
4. **直链探测与实际下载发起两次 GET。** `direct.py:23-25`（HEAD/Range GET）后 `download_item` 再全量 GET，部分 CDN 对 Range 与全量行为不一致，可接受；如稳妥可复用探测连接结果。
5. **图集条目数量无上限。** `resolver\douyin.py:106-113`、`xhs.py:117-120` 对图集全部入列，仅单文件 20MB 限制；异常多图笔记会放大 CPU/磁盘占用。建议加上限（如 20 张）。
6. **版本/环境说明不一致。** `requirements.txt:1` 写“Python 3.11+”、`scripts\setup_venv.bat:9` 提示 3.10+，spec 运行环境为 3.13；无功能影响，建议统一口径。
7. **HTTP 失败重试/重连策略可加抖动与熔断。** worker BRPOP 异常固定 sleep 2s（`worker.py:90-93`）、MySQL 短连接每次新建（`db.py:18-30`），低负载足够；高可用增强方向可加指数退避与连接池。
8. **XHS cover 字段可能不是合法 URL。** `d:\huichuang-Projects\ai-worker\python\wm-service\app\resolver\xhs.py:111-114` 取 `image.firstFrameFileid`（文件 ID token 而非可访问 URL）。当前 runner 不使用该字段（封面由 ffmpeg 生成，`runner.py:148`），无实际故障，仅提示后续若启用需换 URL 字段。

### 明确“未发现问题”的维度

- **命令注入**：ffmpeg/ffprobe 全部走 argv 数组（`common.py:79-93`），无 shell=True；delogo 滤镜参数全部来自 int 元组格式化（`video.py:408-412`）；concat 列表文件名是内部生成的 `piece{i}.mp4`（`video.py:555-558`）；-ss/-to 为内部浮点格式化。未发现命令注入面。
- **路径穿越**：上传源文件经 `ensure_within_root` 对 realpath 做 upload_root 前缀校验（`common.py:42-48`，runner 调用点 `runner.py:102`）；产物路径各组件（wm_subdir/媒体类型/Ym/d/taskid_uuid）均为内部常量或受控值；PHP 侧文件落盘走 core `CoreUploadService`（`UploadSupport.php`），绝对路径由 public_path+受管 url 拼接（`WmService.php:198-199`），且 Python 不信任该路径仍做越界校验。未发现穿越问题。
- **文件上传校验**：PHP 扩展名白名单（图/视）+ 大小预校验 + core 上传服务 MIME 校验双层（`WmService.php:176-196`）；Python 侧再按魔数嗅探判定类型、不信任扩展名（`common.py:22-39`、`http.py:65-78`）。未发现可绕过路径。
- **内部接口鉴权**：`/metrics`、`/internal/tasks` 强制 Bearer（`http_app.py:46-53,90,101`），空 token fail-closed；C 端三个路由在登录中间件组内（`route.php:20,86-88`）；WS 握手 JWT 鉴权（`Websocket.php:83-103`）。
- **WS 信封同构**：`publisher.py:42-64` 与 `CoreTaskPushService.php:47-66` 逐字段一致（msg_id 摘要规则、result 仅成功下发、consume/refund_status/publish_status、信封 type/data/ts 一致）；重连快照按任务表全类型推送（`Websocket.php:212-229`），watermark 任务天然在列。
- **首页“AI生成中”计数**：`TaskQueryService::todoFunnel()` 统计全部类型 status IN(0,1)（`TaskQueryService.php:113-115`），前端 `bindTaskDataSync` 对任意任务事件防抖刷新、`TASK_TYPE_NAME` 已补 `watermark: '去水印'`（`aiwx.ts:10-16,187-193,124-135`）；watermark 任务入库即参与计数，终态自动回落，接线正确。
- **配额/VIP 口径**：提交三类身份分支（closed/remain/vip）正确（`WmService.php:64-70` 与 `CoreVipService.php:94-109`）；Python `is_vip`（`repository.py:193-203`）与 PHP `isVip`（`CoreVipService.php:28-35`）语义一致（status=1 且 expire_date≥当天）；会员成功路径不写 quota（`runner.py:209,227`）。
- **无第三方商业外联**：resolver 仅访问用户给定平台页面/CDN 与基础设施，代码中不存在任何去水印 SaaS/解析付费 API。
- **依赖与部署骨架**：requirements 全量钉版本（`requirements.txt`）；venv 一键脚本、Windows run.bat、Linux sh/systemd/supervisor、config.example、.gitignore（忽略 .venv/config.ini/logs/tmp）齐备；`python -m compileall` 全量编译通过；启动前 MySQL/Redis 自检失败即退出（`run.py:66-74`）。
- **资源清理**：每任务独立 tmp 子目录，finally rmtree（`runner.py:238-241`），启动清 24h 孤儿（`run.py:28-41`）；下载超限即时删临时文件（`http.py:107-113`）；ffmpeg 任务级超时杀进程树（`common.py:124-159`）。

---

## 三、Acceptance Criteria 逐条核对

> 说明：本次为代码审查 + 静态验证（compileall 通过），未实跑端到端请求。以下“依据”均给出代码位置；运行时实测项标注“未实跑”。

| AC | 结论 | 依据 |
|---|---|---|
| AC-1 服务可启动/探活/依赖复现 | **通过（代码级，未实跑 curl）** | `/health` 含 redis/mysql/ffmpeg/队列深度/worker 存活，失败返回 503（`http_app.py:55-88`）；`/metrics` 无 Bearer 返回 401（`http_app.py:46-53,90`）；启动自检（`run.py:66-74`）；requirements 全钉版本；compileall 通过。Minor-2（只探 ffprobe、worker 死亡不影响健康）建议跟进。 |
| AC-2 上传图片端到端 | **部分通过** | 状态机 0→1→2、产物路径 `upload/ai_wenxiu/wm/image/Ym/d/`（`runner.py:120-194`）、material 同事务写入、quota upsert、result.material_id 回填链路代码完整；但 M2 指出该“事务”行锁在 autocommit 下不生效、material 无唯一键，并发重影下不满足“四处一致”；未实跑。 |
| AC-3 上传视频端到端 | **部分通过** | ffprobe 校验、超限拒绝、抽帧/聚类/时间轴分段/delogo/concat/封面/进度解析实现完整（`video.py` 全文件）；音频转 AAC（保留音轨，满足 AC-3 底线，见 Sugg-2）；**旋转素材行为未验证且代码无保障（M4）**；未实跑，音画同步/时长 ±0.3s 需 TR-6 实测。 |
| AC-4 WS 实时反馈 + 计入生成中 | **通过（代码级）** | Python 三事件 PUBLISH 与 PHP 信封逐字段同构（`publisher.py` vs `CoreTaskPushService.php`）；认领即推 10%、节流进度、终态帧（`worker.py:132,156-170`、`runner.py:227-230`）；重连快照覆盖 watermark；首页 funnel 与 toast 接线齐全。 |
| AC-5 在途任务唯一 | **部分通过** | 串行提交：count→拒绝正确（`WmService.php:76-84`），前端双按钮置灰（`index.vue:50,74,177`）；**并发双发无锁/无唯一键兜底（M3）**，不满足“DB 无新任务行”的严格并发语义。 |
| AC-6 任务列表展示与实时更新 | **通过（代码级）** | `GET watermark/tasks` 近 50 条、本人限定、倒序、字段齐全（`WmService.php:124-140,235-274`）；页面 onLoad 拉取、watchAllTasks 过滤 watermark、终态静默重拉、成功预览/失败原因均实现（`index.vue:186-224,292-309`）。Minor-7（onHide 未解绑）不影响功能。 |
| AC-7 配额规则 | **部分通过** | 三身份提交分支、会员不读不写 quota、失败路径不计数（`runner.py` 失败分支在 worker 层，`worker.py:145-152` 不调 finalize_success）均正确；但 M2 的并发/跨终态写入会造成非会员重复计数、失败任务被扣次，修复前 AC-7 的严格口径不成立。 |
| AC-8 链接模式 | **部分通过** | 平台识别、短链跟随（max_redirects=8）、抖音 _ROUTER_DATA/RENDER_DATA 提取+图集+playwm→play、小红书 __INITIAL_STATE__（undefined→null）+视频/图集、直链 Content-Type/后缀兜底、视频号提交期拒绝、错误码分支完备（resolver 全目录 + `WmService.php:149-153`）；真实抖音/小红书未线上实测——**已知接受项**；另存在 M6 SSRF 加固项。 |
| AC-9 高可用与故障恢复 | **部分通过** | processing zset 租约、选主锁、ZREM 认领、attempts 上限置失败、终态条件 UPDATE、成功幂等短路（status=2）均已实现；但存在 M1（消息丢失/无孤儿扫尾→永久卡 0/1）与 M2（重复 material/quota、失败任务被记成功账）两类硬伤，kill -9 在租约登记之后的场景可恢复，在登记窗口/特定失败时序不可恢复。 |
| AC-10 安全与自托管 | **部分通过** | Bearer 鉴权、上传白名单+魔数、路径 realpath 防穿越、argv 化防注入、输出目录受控、无商业外联均满足；SSRF 内网/重定向拦截到位但有 DNS rebinding 缺口（M6）；默认绑 127.0.0.1:8300（`config.example.ini:6`）。 |
| AC-11 处理质量（观感 rubric） | **未验证** | 算法实现完整：边缘带白/彩/深色三通道、形态学+连通域过滤、面积上下限回退原样输出（`image.py:34-151`）；视频静态先验+白字复现率投票+四角聚类+并集框+delogo 边距钳制（`video.py:123-412`），无水印走 `-c copy` 不损画质（`video.py:509-533`）。未用标准样图实测，无法给 1-5 分；M4 旋转问题可能影响部分素材评分。 |
| AC-12 部署运维完整性 | **部分通过** | venv/requirements/config 模板/Win/Linux 启停脚本/systemd/supervisor/日志滚动/health/metrics 齐备；systemd cgroup 收编合理。扣分项：M5（addon 分发副本缺文件、两副本 aiwx.ts 不一致）；Linux 脚本未实跑——**已知接受项**；Minor-4（supervisor 进程组回收配置缺失）。 |

---

## 四、总体结论

**不建议直接进入交付。** 主体工程质量较高：状态机/WS 同构、配额口径、上传校验、命令注入与路径穿越防护、可观测性与部署骨架均落实到位，Python 代码编译通过、结构清晰。但有 6 个 Major 涉及 spec 的核心承诺（可靠队列不丢、终态幂等不重复计费、在途唯一、旋转素材正确性、addon 包可独立交付、SSRF 加固），其中：

**交付前建议必修（成本低、收益高）：**

1. M3 在途唯一加 Redis SETNX 占位或事务锁（PHP 侧小改）。
2. M2 finalize 事务显式 BEGIN/COMMIT + material 加唯一键 + 拦截 status=3（一个迁移索引 + 十余行代码）。
3. M1 取任务 Lua 原子化 + 增加 DB 孤儿任务扫描（可顺带消费已有的 `timeout_at` 字段）+ 终态写库失败不 ack。
4. M5 同步分发副本 `websocket.ts` 并对齐 `aiwx.ts`（纯同步动作）。

**可用实测替代改码（需留证据）：**

5. M4 用真机 rotate=90 的 .mov 走一轮 TR-6；若当前 ffmpeg 实测保留旋转且坐标系一致，凭证据降级为已知项；否则按建议固化方向处理。

**可交付后首个迭代加固：**

6. M6 DNS rebinding 钉连；Minor 1/2/4/9/10 等可观测性与运维增强。

**已知并接受的残余风险（按委托说明复述，不作为阻断项）：**

- 真实抖音/小红书链接未做线上实测（直链与平台分支代码、SSRF 拦截已静态核对）；
- 小程序 UI 仅构建通过，未在微信开发者工具实测；
- 开发环境为 Windows、生产为 Linux，systemd/supervisor/sh 脚本未在 Linux 实跑。

---

## 五、修复闭环记录（2026-10-08）

全部 6 个 Major 已修复并完成运行时验证；高性价比 Minor 一并修复。验证用临时脚本（tmp_verify.py / tmp_migrate.py）与全部造数任务、产物、临时文件均已清理；开发库恢复到无 wm 测试任务状态（task/material 计数为 0）。

### M1 可靠队列丢失窗口 + 无孤儿扫描

- `queue.py` 重写为「waiting → landing（BRPOPLPUSH 原子搬运）→ Lua 注册 processing 并 LREM landing」两段原子提交；`enqueue` 改 Lua 去重（waiting/processing 同载荷跳过）；新增 `drain_landing()`、`queued_task_ids()`、`release_slot()` 与静态 `slot_key()`；`ack` 幂等（ZREM + LREM landing）。
- `worker.py` 恢复器三级顺序：landing 残留 → processing 租约过期 → DB 孤儿扫描（`list_active_wm_tasks()`，status=0 且超 `ORPHAN_WAIT_GRACE=120s`、且消息确不在队列中才重投）；claim CAS 失败不删他人租约；`_safe_fail` 写库失败则不 ack，保留租约待下轮恢复；终态确定后才释放在途槽；恢复时达最大重试置 WM_RETRY_EXHAUSTED。`recover_interval` 60→15s。
- 证据（live 阶段，三 worker 在线）：landing 残留、processing 租约过期、纯 DB status=0 孤儿（造数时间 300s 前）三个任务均被自动恢复为 status=2、progress=100、资产各 1 条、产物文件存在。队列原语（stopped 阶段直连 Redis）：enqueue 去重、claim 后 processing 唯一、ack 清空、drain_landing 识别孤儿、release_slot 全部 PASS。

### M2 finalize 幂等不持锁 / material 无唯一键 / status=3 可写成功

- `finalize_success` 改用显式 `conn.begin()` 事务 + `SELECT ... FOR UPDATE`：已成功返回既有 material_ids；status NOT IN (0,1) 一律抛 `WM_TASK_ALREADY_FINISHED`；material INSERT 带 `wm_seq`；配额仅首次落账且日期固定北京时间（`today_cn()`，Windows 无 tzdata 时回退固定 UTC+8）。
- install.sql：material 新增 `wm_seq INT UNSIGNED DEFAULT 0` 与虚拟生成列 `wm_uniq_task = related_task_id*100+LEAST(wm_seq,99)`（仅 cat='ai_generated' AND tag='去水印' AND related_task_id>0 生效）+ `UNIQUE KEY uk_wm_task_seq`；开发库迁移已执行并确认索引存在。
- 证据（repo 阶段，FAKE_MEMBER 自清）：重复 finalize 资产不增且返回同 id、首次成功配额=1、3 图集 3 条资产配额=2、失败任务再写成功被 WM_TASK_ALREADY_FINISHED 拦截且资产 0、配额不变；VIP member 当日不产生配额行。全部 PASS。

### M3 PHP 在途唯一 check-then-insert

- `WmService.php` submit 开头 `SET NX EX 1800` 占槽（`aiwx:wm:slot:{site}:{member}`）；抢槽失败时复核 DB 无在途任务则判崩溃残留、删 key 重试一次；Redis 不可用 fail-open 回退 DB 校验；任何未成功入队路径（catch Throwable）释放槽；入队即失败的任务补发 `EVENT_FAILED` 推送（兼修 Minor-12）。`php -l` 通过。

### M4 旋转元数据

- `probe()` 读 side_data/tags 旋转，返回 coded_width/coded_height/rotation，90/270 时互换显示宽高；抽帧 `CAP_PROP_ORIENTATION_AUTO=1` + 未旋正兜底 np.rot90；转码与封面命令移除全部 `-noautorotate`（重编码烘焙旋转），无水印 `-c copy` 保留原矩阵，封面默认正向。
- 证据：造数 848x480 + Display Matrix rotation=90 + sine 音轨 + 角标视频，probe 正确报 coded 848x480/rot=90/显示 480x848；任务经检测命中走重编码路径，成片 rot=0、coded=显示 480x848、音轨保留、时长 4.0s；源/成片抽帧目检均为正向竖屏，角标在成片中去除。全部 PASS。（另一次角标未被启发式命中时走 copy 路径，矩阵保留、显示尺寸同样正确。）

### M5 addon 分发副本缺 websocket.ts

- 已将 `websocket.ts`、`aiwx.ts` 从主仓同步到 `niucloud/addon/ai_wenxiu/uni-app/utils/`，SHA256 两两一致；其余 24 个历史漂移文件与本次功能无关，未改动。

### M6 DNS rebinding

- `http.py` 新增 `_resolve_public_ip()`：域名所有候选地址必须公网（拒绝 loopback/private/link-local/reserved/multicast/unspecified），IPv4 优先；`PinnedIpSyncBackend` 覆盖 httpcore sync backend 的 connect_tcp，以校验后 IP 钉连，TLS SNI/证书仍按原域名校验。
- 证据（ssrf 阶段）：HTTPS 访问 https://www.baidu.com/ 经钉连返回 200（SNI/证书正常）；127.0.0.1、169.254.169.254、10.0.0.1 全部被阻断。全部 PASS。

### 顺带修复的 Minor

- /health 增加 workers_ok（expected>0 且 alive=0 时返回 503）；token 比较改 hmac.compare_digest（空 token fail-closed）；run_ffmpeg stderr 改 PIPE 并消费线程、失败 detail 带出 ffmpeg stderr；supervisor.conf 加 stopasgroup/killasgroup、wm-service.sh 停止时先杀子进程组；配额日期固定北京时间。

### 其他回归

- `python -m compileall app run.py` 通过；`php -l WmService.php` 通过；`npm run build:mp-weixin` 主包 + 3 个 addon（含 ai_wenxiu）构建合并成功（[build-mp-all] OK）。

### 仍接受的残余风险（不变）

真实抖音/小红书链接线上实测、微信开发者工具 UI 实测、Linux 守护脚本实跑，留待生产部署后首轮验证。
