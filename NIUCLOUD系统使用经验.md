# NIUCLOUD SaaS 系统使用经验

> 基于官方使用文档（本地镜像 `help_doc/`）整理，面向运维、实施、二开工程师。覆盖从安装部署到线上运维的全链路操作流程。

---

## 一、环境要求

### 1.1 服务端

| 组件 | 最低版本 | 推荐版本 | 说明 |
|------|----------|----------|------|
| PHP | 8.0 | 8.0+ | 必须开启扩展：fileinfo、redis、openssl、pdo_mysql、mbstring、curl、gd、xml |
| MySQL | 8.0 | 8.0+ | 字符集 utf8mb4；**必须去掉 `NO_AUTO_VALUE_ON_ZERO` 和 `ONLY_FULL_GROUP_BY`**，否则后台打不开 |
| Redis | 5.0 | 7.0+ | 队列和缓存共用；不开 Redis 时队列只能用 sync/database 驱动 |
| Nginx | 1.18+ | 最新 | Apache 也能用但 Nginx 首选 |
| PHP内存 | 256M | ≥512M | 安装/升级时不够会直接崩 |

### 1.2 前端构建

| 端 | 环境 | 构建命令 | 输出目录 |
|----|------|----------|----------|
| Admin | Node ≥ 18 | `npm run build` | `admin/dist` → 上传到 `public/admin/` |
| uni-app | Node ≥ 18 | `npm run build:h5` / `build:mp-weixin` | H5 → `public/wap/`；小程序用微信开发者工具上传 |
| Web | Node ≥ 18 | `npm run generate` | `web/dist` → 上传到 `public/web/` |

### 1.3 Nginx 伪静态（必须配）

```nginx
location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.php?s=$1 last;
    }
}
```

Apache 对应 `.htaccess` 框架根目录已有，直接用。

---

## 二、安装部署

### 2.1 本地快速安装（phpStudy）

1. phpStudy 创建网站，PHP 8.0+、MySQL 8、Redis 开启
2. 运行目录设为 `niucloud/public`（**不是项目根！**）
3. 源码放进去，`composer install` 跑一次（如果没装过 vendor）
4. 配好伪静态
5. 浏览器访问，自动进入安装向导，填数据库连接信息
6. 安装完成后，**立刻改数据库 sql_mode**，去掉 `NO_AUTO_VALUE_ON_ZERO` 和 `ONLY_FULL_GROUP_BY`

### 2.2 宝塔部署

1. 宝塔面板 → 软件商店 → 装 Nginx / PHP 8.0 / MySQL 8 / Redis
2. 创建网站：填域名、运行目录选 `niucloud/public`、PHP 版本选 8.0+
3. 上传源码到网站根目录
4. 网站设置 → PHP 设置 → 安装 Redis 扩展
5. 网站设置 → 伪静态 → 填入 Nginx 规则
6. 网站设置 → 防跨域攻击 → **取消勾选**（否则报 `is_dir()` 错）
7. MySQL 设置 → sql_mode → 去掉两个选项
8. 安全组开放端口：80 / 443 / 8000 / 3306 / 8888 / 21（21 FTP / 8000 phpStudy / 8888 宝塔）

### 2.3 授权绑定

- 官网购买应用/插件后，后台「平台端 → 授权信息」填授权码 + 授权密钥
- 授权绑定后才能在线升级
- **独立版授权和 SaaS 版授权不能互绑**，绑错直接报"授权码或授权密钥错误"
- 非官方来源插件不需要授权

### 2.4 首次安装后必须做的事

1. 宝塔面板 PHP 内存调 ≥ 512M
2. MySQL sql_mode 去两个选项
3. 运行目录确认是 `public`
4. `.env` 里 `APP_DEBUG = true`（调试用），上线前改回 `false`
5. 前端三个端（admin / uni-app / web）各编译一次，或用云编译

---

## 三、云编译 vs 本地编译

### 3.1 云编译（推荐）

后台「平台端 → 云编译」一键搞定，覆盖 admin / uni-app / web 三个端。

**必须云编译的场景**：
- 安装插件后（插件自带前端代码）
- 升级框架后
- 修改了菜单、DIY 组件、万能表单等框架内置前端配置后
- 多插件一起装时，**全部装完最后整体编译一次**，逐个编译容易漏

**云编译失败常见原因**：
- Node.js 内存不够：改 `admin/package.json` 里的构建命令，加 `NODE_OPTIONS=--max-old-space-size=8192`（uni-app 和 web 同理）
- 依赖版本冲突：`echarts` 版本不对，改 `admin/package.json` 里 echarts 版本
- 先升插件后升框架导致云编译 `debounce` 错误：按官方补丁修

**第三方云编译服务器**（自搭建）：
- Node ≥ 18
- 下载编译服务：`https://gitee.com/niucloud-team/cloud_build`
- `npm install && npm run serve`
- 后台「开发者 KEY 设置」里填地址 → 测试连通

### 3.2 本地编译

```bash
# Admin
cd admin && npm install && npm run build
# 产物 dist/ → 上传 public/admin/，或云编译覆盖

# uni-app H5
cd uni-app && npm install && npm run build:h5
# 产物 dist/build/h5/ → public/wap/

# uni-app 小程序（开发调试用 dev）
npm run dev:mp-weixin     # 开发模式，改完热更新
npm run build:mp-weixin   # 打包模式
# 产物 dist/build/mp-weixin/ → 微信开发者工具上传

# Web
cd web && npm install && npm run generate
# 产物 dist/ → public/web/
```

> **云编译和本地编译不要混用**。云编译会覆盖 `public/admin/`、`public/wap/`、`public/web/`，本地编译产物也会被云编译冲掉。团队里定一个就行。

---

## 四、平台端核心功能操作

### 4.1 站点管理

后台「平台端 → 站点管理」：

**套餐模式**（SaaS 核心）：
1. 创建站点套餐 → 勾选要包含的应用/插件
2. 创建站点 → 选择套餐 → 填域名
3. 站点自动加载套餐里的插件菜单和权限

**站点列表操作**：
- 切换站点控制 → 一键禁用某个站点（不让它访问）
- 站点初始化 → 重置站点数据
- 删除站点 → 同时清理 `sys_menu` 里的站点级菜单

### 4.2 插件管理

后台「平台端 → 插件管理」：

| 标签页 | 含义 |
|--------|------|
| 已安装 | 当前在线的插件，可升级 / 卸载 |
| 未安装 | 本地有文件但没启用的插件 |
| 已购买 | 从官方买了但还没下载到本地的 |
| 最近更新 | 框架有新版本但还没同步的 |

**安装流程**：未安装 → 搜索插件 → 点安装 → 等环境检查通过 → **云编译** → 站点套餐里勾选 → 刷新站点

**卸载流程**：已安装 → 点卸载 → 插件自动清理自己的菜单和数据（如果插件 `unInstall.sql` 写了）

### 4.3 系统升级

后台「平台端 → 系统升级」：
- 查看当前版本 / 最新版本
- 点"检查更新"
- 升级前**先备份数据库和代码**
- 升级顺序：**先升插件，再升框架**。反过来会出 `debounce` 编译错误
- 升级完云编译一次

### 4.4 代码生成器

后台「平台端 → 代码生成」：

**流程**：
1. 设计数据表（或导入已有表）
2. 添加数据表 → 选要生成的插件（默认生成到框架 app 下）
3. 数据表编辑：基础信息 → 字段设置 → 生成配置（选是否生成 adminapi / api 两端）→ 关联设置
4. 预览生成代码
5. 同步到插件目录（自动写 Model / Service / Controller / Validate / Route / Menu）
6. 或下载 zip 手动放

**生成配置里可选项**：
- 是否生成 adminapi 控制器
- 是否生成 api 控制器
- 生成的 Service 层分 admin / api / core 三份
- 自动写菜单字典

### 4.5 计划任务

后台「平台端 → 计划任务」：

**前提**：Redis 扩展已开、宝塔 Supervisor 守护进程已加。

**运行方式**（二选一）：
- Supervisor 守护进程：运行目录 `niucloud/`，启动命令 `php think worker:start`，保持常驻
- 系统 crontab：每分钟触发 `php think cron:run`

**重启生效**：任何计划任务参数变更，都要 `php think worker:stop && php think worker:start`

**手动执行**：点"立即执行"按钮，或 `php think cron:schedule`

### 4.6 数据字典

后台「平台端 → 数据字典」：
- 管理框架内置的字典项（支付类型、订单状态等）
- 插件也可以定义自己的字典，后台会自动合并显示
- 可以增删改数据项、排序、写备注

### 4.7 缓存更新

后台「平台端 → 缓存更新」：
- 清 `runtime/` 下的 ThinkPHP 临时文件
- 表结构新增字段不显示？先清缓存刷新页，不行再重启服务器

---

## 五、站点端核心功能操作

### 5.1 会员体系

**会员列表**：搜索筛选（等级 / 标签 / 注册时间）、批量操作、添加会员、会员详情页直接操作余额/积分/佣金
**会员等级**：新增等级 → 设所需积分 → 等级权益（折扣率、免邮等）
**积分规则**：定义"注册送多少"、"每消费 1 元送多少"
**成长值规则**：和积分类似，独立计算，用于自动升级
**会员标签**：自定义标签，给会员打标记，支持按标签筛选
**会员设置**：注册方式（手机号/邮箱/无需验证）、默认等级、默认积分

### 5.2 资金流水

**会员余额 / 会员佣金**：手动加减、查看明细、批量操作
**会员提现**：申请记录、审核、打款。微信零钱打款需要：
1. 微信商户号开通"商家转账到零钱"权限
2. 选择转账场景 → 提交材料
3. 站点端「支付设置」里填转账场景 ID 和 APIv3 密钥
4. 注意：APIv3 密钥和商户号必须是同一个主体下的

**财务流水**：所有资金进出的审计日志

### 5.3 支付配置

站点端 → 支付设置：

**微信支付**：商户号 + APIv3 密钥 + 商户私钥 + 商户证书序列号 + 回调地址
**支付宝支付**：应用 ID + 应用密钥 + 支付宝公钥 + 商户私钥 + 回调地址
**PC / H5 / APP / 小程序 / 公众号**：每个端单独开关和配置

**小程序支付常见坑**：
- 报"由于小程序违规，支付功能暂时无法使用" → 微信公众平台「支付与交易 → 订单管理」里填写 path
- Token 校验失败 → 先在商城后台填好保存配置，再提交微信后台

### 5.4 消息通知

**消息模板**：按场景分类（会员注册、订单状态变更、提现申请等），每个场景可开关
**短信设置**：三家服务商选一家启用（牛云 / 阿里云 / 腾讯云），填 AccessKey / SecretKey / 签名 / 模板 ID
**发送记录**：短信和模板消息的历史发送日志

### 5.5 存储配置

站点端 → 存储设置：七牛云 / 阿里云 / 腾讯云三选一
- Bucket + AccessKey / SecretKey + 域名
- 上传时自动走配置的存储，不再存本地

### 5.6 地图配置

**腾讯地图**：需要 Key。地图鉴权失败时，**平台端和站点端都要填 Key**，配置完云编译

### 5.7 海报管理

站点端 → 海报管理：
- 添加海报 → 选模板 → 上传背景图 → 拖位置/调大小
- 支持二维码、文字、图片、会员头像等组件
- 可预览、复制、编辑

### 5.8 打印管理

站点端 → 打印管理：
- **打印机管理**：接入云打印机（芯烨等），填机器编码
- **打印模板管理**：小票 / 电子面单两种模板，支持拖组件
- **电子面单完整流程**：
  1. 添加物流公司（快递鸟 / 菜鸟）
  2. 连接/安装打印机驱动
  3. 快递鸟账号拿 ID 和 API Key
  4. 后台订单管理 → 电子面单设置
  5. 打印机端口配置
  6. 模板设置 → 打印电子面单

### 5.9 万能表单

站点端 → 万能表单：
1. 创建表单 → 选字段类型（单行/多行/选择/图片上传/日期/时间等）
2. 设置填写规则（必填/只读/默认值）
3. 推广：生成二维码和链接
4. 数据统计：看提交情况
5. 提交成功页：自定义跳转或提示语
6. 分享设置：允许用户转发

### 5.10 微页面 DIY 装修

站点端 → 微页面：
- 页面列表 → 添加页面
- 选页面类型：首页 / 个人中心 / 自定义页面（可独立分享）
- 装修：从左侧拖组件（轮播图、导航、商品、文本、图片等）到画布
- 预览 → 使用（绑定到首页 / 个人中心 / 某个路由）
- 编辑 / 复制 / 删除

**本地装修环境配置**：
1. `uni-app/.env.development` 填 API 地址和图片服务器地址
2. `npm run dev:h5` 编译
3. 装修后台「装修域名配置」里填编译后的地址

### 5.11 微信公众号接入

站点端 → 微信公众号：
1. 接入流程：填 AppID / AppSecret / Token / EncodingAESKey
2. 自定义菜单：可视化编辑器，支持点击 / 发送消息 / 跳转网页
3. 模板消息：选场景 → 填模板 ID
4. 自动回复：**XML 文件名首字母必须大写**，否则自动回复不通；改完配置重新关注公众号才生效

### 5.12 微信小程序接入

站点端 → 微信小程序：
1. 填 AppID / AppSecret
2. Token 校验失败 → workerman 进程用户权限不对（不要用 root 跑），或目录权限改为 777
3. 消息推送 access_token 获取失败 → 同上权限问题

### 5.13 主题风格 / 底部导航

**主题风格**：选配色方案 → 新增颜色变量 → 保存 → 客户端自动变色
**底部导航**：uni-app 底部 TabBar，可视化编辑器配置图标和跳转

---

## 六、域名与多站点

### 6.1 站点独立域名

站点端「域名配置」填独立域名 → 宝塔加站点 → 指定运行目录为 `public` → 通过独立域名访问 wap/web 端。

### 6.2 多端独立域名

- Admin 后台：`admin.xxx.com`
- 网站 H5：`m.xxx.com`
- Web 端：`www.xxx.com`

各自在 Nginx 里配置 server block，运行目录都是同一个 `niucloud/public`。

---

## 七、FAQ 速查

### 7.1 安装阶段（installFAQ）

| 问题 | 解法 |
|------|------|
| 未找到 admin 源码所在目录 | 检查站点运行目录是不是 `niucloud/public`，源码是否上传完整 |
| `is_dir()` 报错 | 宝塔网站设置 → 取消"防跨域攻击" |
| PHP 内存耗尽 | 宝塔 PHP 设置 → 内存改 512M 以上 |
| `mkdir(): Permission denied` | 手动在 niucloud 同级建 `upgrade` 目录，或站点权限设 777 |
| Node.js 云编译内存溢出 | 改 package.json 构建命令加 `NODE_OPTIONS=--max-old-space-size=8192` |
| 插件不适配框架版本 | 改插件 `info.json` 的 `support_version` 为当前框架版本 |
| 升级框架报错 | 改 admin/package.json 里 echarts 版本 |
| 云编译 debounce 错误 | 按官方补丁修（先升插件后升框架才出这个） |

### 7.2 使用阶段（useFAQ）

| 问题 | 解法 |
|------|------|
| 授权码/密钥错误 | 核对购买的应用和框架版本匹配，独立版 / SaaS 版不能互绑 |
| 后台打不开 | MySQL sql_mode 去 `NO_AUTO_VALUE_ON_ZERO` 和 `ONLY_FULL_GROUP_BY`；手动把 admin 站点 site_id 改 0 |
| 关调试模式 | `.env` 里 `APP_DEBUG=false` |
| 本地开发域名不匹配 | 用 localhost 或配与授权域名一致的虚拟域名 |
| 新增字段不显示 | 清 `runtime/` 缓存；不行就重启服务器 |
| 公众号自动回复不通 | XML 文件名首字母大写；改完重新关注公众号 |
| 提现待转账 | 后台「财务 → 会员提现」手动处理 |

### 7.3 开发阶段（devFAQ）

| 问题 | 解法 |
|------|------|
| 菜单加了不出现 | 后台「开发 → 平台/站点菜单 → 重置菜单」+ 配置角色权限 |
| 本地调试要站点 ID | `uni-app/.env.development` 里配站点 ID |
| 装修本地环境 | `uni-app/.env.development` 填 API 和图片地址；H5 编译后填装修域名 |

### 7.4 配置阶段（configFAQ）

| 问题 | 解法 |
|------|------|
| 接口请求错误 | 伪静态没配（见 Nginx 伪静态节） |
| 小程序支付"违规" | 微信后台「支付与交易 → 订单管理」填 path |
| 小程序 Token 校验失败 | 先商城后台保存，再提交微信 |
| 小程序 access_token 获取失败 | workerman 权限（不要 root 跑），目录 777 |
| 地图鉴权失败 | 平台端和站点端都填 Key，配完云编译 |
| 腾讯地图 Key 无效 | 腾讯地图控制台开通"腾讯位置服务" |
| 微信小程序上传限制 | 开发者工具里去掉不相关文件减小包体 |
| 绑定手机号报错 | 检查短信服务商配置 |
| 改端口/域名 | `.env` 里 APP_HOST、APP_PORT |
| 网站图标不显示 | 换 `favicon.ico`（必须根目录放同名文件） |

### 7.5 服务器 / 代码阶段

| 问题 | 解法 |
|------|------|
| 接口超时 | 宝塔 PHP 最大执行时间改 300；Nginx 超时改 300；Redis 检查连通性 |
| 类找不到（class not exists） | `composer dump-autoload`；插件未安装导致 autoload 没生成 |
| 表自动更新报错 | DB 权限不够；或框架版本不兼容 |

---

## 八、推荐运维流程

### 8.1 上线前检查清单

- [ ] PHP / MySQL / Redis 版本达标
- [ ] MySQL sql_mode 已去两个选项
- [ ] PHP 内存 ≥ 512M，上传大小 ≥ 20M，最大执行时间 ≥ 300s
- [ ] Nginx 伪静态已配
- [ ] `.env` 里 `APP_DEBUG = false`
- [ ] 三个前端（admin / uni-app / web）都已编译或云编译
- [ ] 云编译、Redis 队列、计划任务 supervisor 守护都起来了
- [ ] 所有支付、短信、地图、存储配置已填并测试通过
- [ ] 数据库已备份一次
- [ ] HTTPS 已配好（宝塔一键申请 Let's Encrypt）

### 8.2 日常维护

- **缓存**：发现数据不对先清 runtime，再看是不是表缓存（model 里 `getModelColumn` 有缓存）
- **日志**：runtime/log/ 按日期分文件，有报错先看最新一个
- **升级**：先升插件 → 再升框架 → 云编译 → 功能回归
- **权限**：目录不要设 777 长期跑，只在临时修复后改回 owner:www 755
- **队列**：Redis 队列挂了 → 先查 Redis 通不通 → 再查 Supervisor 进程状态

### 8.3 紧急回滚

1. 云服务器快照回滚（最快）
2. 数据库 SQL 备份回滚
3. 代码 git 回滚（框架和插件分仓库）

---

## 九、配置速查表

### .env 关键项

```env
APP_DEBUG = false            # 上线必须改
APP_HOST = 127.0.0.1
APP_PORT = 8000
DB_HOSTNAME = 127.0.0.1
DB_DATABASE = your_db
DB_USERNAME = root
DB_PASSWORD = your_pwd
DB_PREFIX = nc_              # 表前缀，不要改，改了整站表名变
REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379
QUEUE_STATE = true           # 开启队列
QUEUE_DRIVER = redis         # 推荐 redis
QUEUE_NAME = niucloud
```

### NiucloudServer 内置 Loader 一览

| Loader | 职责 |
|--------|------|
| LoginLoader | 登录鉴权 |
| PayLoader | 支付驱动（微信 / 支付宝） |
| SmsLoader | 短信驱动（牛云 / 阿里 / 腾讯） |
| UploadLoader | 存储驱动（本地 / 七牛 / 阿里 / 腾讯） |
| PosterLoader | 海报生成 |
| PrintLoader | 小票打印 |
| TemplateLoader | 模板消息 |

---

## 十、参考文档

- 官方使用文档本地镜像：[help_doc/README.md](./help_doc/README.md)
- 官方开发文档本地镜像：[dev_doc/README.md](./dev_doc/README.md)
- 开发经验总结：[NIUCLOUD系统开发经验.md](./NIUCLOUD系统开发经验.md)
- 代码生成器：[help_doc/CodeGenerator/Index.md](./help_doc/CodeGenerator/Index.md)
- 安装指引：[help_doc/InstallGuide/LocalDeployment.md](./help_doc/InstallGuide/LocalDeployment.md)
- 宝塔域名配置：[help_doc/InstallGuide/BTPanelDomainConfig.md](./help_doc/InstallGuide/BTPanelDomainConfig.md)
- 云编译：[help_doc/UserGuide/PlatformFunctions/CloudCompilation.md](./help_doc/UserGuide/PlatformFunctions/CloudCompilation.md)
- 计划任务：[help_doc/other/scheduled-task-config.md](./help_doc/other/scheduled-task-config.md)
- 支付配置：[help_doc/UserGuide/SiteFunctions/PaymentSettings.md](./help_doc/UserGuide/SiteFunctions/PaymentSettings.md)
- 电子面单：[help_doc/other/electronic-waybill-config.md](./help_doc/other/electronic-waybill-config.md)
