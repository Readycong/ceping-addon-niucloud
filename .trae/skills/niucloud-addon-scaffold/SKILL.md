---
name: niucloud-addon-scaffold
description: Scaffold a new niucloud addon with backend plus admin, uni-app and web frontend skeleton. Use when creating or initializing a niucloud addon, plugin or application, not when editing an existing addon.
---

# Niucloud Addon Scaffold

为 niucloud PHP SaaS 框架生成一个**可直接安装、自带示例 CRUD** 的插件/应用骨架。骨架生成在 `niucloud/addon/{key}/`，结构与命名严格遵循框架约定。

## 1. 收集参数

向用户确认以下信息（缺失才问，一次问清；已有合理默认值时直接采用）：

| 参数 | 规则 / 默认 |
|---|---|
| key | **必填**。全小写 a-z + 下划线，禁拼音，如 `lottery_draw`。目录名、info.json.key、命名空间全部用它 |
| title | 中文名称，如「抽奖小游戏」 |
| short_title | 菜单简称，2-3 字，默认取 title |
| desc | 一句话描述，可空 |
| type | `app`（主应用，一套系统一个）或 `addon`（独立插件，可多个）。不确定时默认 `addon` |
| author | 默认读 info.json 已有作者，否则问一次 |
| support_version | 默认读 `niucloud/config/version.php` 的当前框架版本（主次版本必须一致，如 `1.1.7`） |

**校验 key 合法性**：`^[a-z][a-z0-9]*(_[a-z0-9]+)*$`。目标目录 `niucloud/addon/{key}/` 若已存在，停止并告知用户，不要覆盖。

## 2. 双端架构与菜单归属（生成前必读）

niucloud 是多站点 SaaS，同一套后端代码服务两类管理端（app_type 见 niucloud/app/dict/sys/AppTypeDict.php）：

| 端 | app_type | 菜单字典 | 入口与 URL | 数据范围 |
|---|---|---|---|---|
| SaaS 总后台（平台端） | `admin` | `app/dict/menu/admin.php` | 平台管理员登录总后台 | 跨站点 |
| 站点后台（租户端） | `site` | `app/dict/menu/site.php` | 站点账号登录后台，页面 URL 前缀 `/site/` | 按 site_id 隔离 |
| 手机/PC 客户端 | `api` | 无后台菜单 | uni-app / web | member |

铁律级事实（最高频踩坑点）：

1. **目录名带 admin ≠ 平台端**：`app/adminapi/`、`app/service/admin/`、三前端里的 `admin/` 目录由平台端与站点端**共用同一批控制器/接口**（URL 均为 `/adminapi/...`），框架按登录站点的 app_type 区分权限与 site_id 上下文。
2. **功能入口归属哪一端，只由菜单字典决定**：框架把 admin.php / site.php 分别写入 sys_menu 的两个 app_type 分区，权限校验只加载当前端菜单按钮（api_url + methods）。
3. 普通业务插件的管理菜单（含本脚手架示例 item）**只写 site.php；admin.php 必须保持 `return [];`**。官方插件 cms、recharge 的 admin.php 均为空数组。
4. 只有"给 SaaS 平台运营方使用的跨站点功能"（如平台资源池、全站审核）才写 admin.php，且必须自行处理无 site_id 上下文。**拿不准就留空；严禁把 site.php 内容对称复制一份到 admin.php。**
5. 菜单字典改动后，到 **SaaS 总后台 → 开发 → 菜单 → 重置菜单** 重新装载（禁止直接改 sys_menu 表）；站点端还需站点套餐勾选插件后才授权。

## 3. 后端四层架构（骨架代码必须遵守）

```
请求 → Controller（参数获取 + 验证 + 调 Service + 响应）
          ↓
       Service（业务逻辑 + 分页查询封装）
          ↓
       Model（表映射 + 搜索器 + 获取器 + JSON/类型转换）
          ↓
       Database
```

**Validate 层**独立在 `app/validate/`，不在四层内，但 Controller 里必须调。

### 3.1 Service 三层基类

| 基类 | 路径 | 适用场景 | 自动注入 |
|------|------|----------|----------|
| `BaseAdminService` | `core\base\BaseAdminService` | 后台管理逻辑 | `site_id` / `username` / `uid` / `app_type` |
| `BaseApiService` | `core\base\BaseApiService` | 前端用户逻辑 | `site_id` / `member_id` / `channel` |
| `BaseCoreService` | `core\base\BaseCoreService` | admin 和 api 都要用的公共逻辑 | 无请求上下文，自己取 |

**骨架生成时必须**：admin 服务继承 `BaseAdminService`，api 服务继承 `BaseApiService`。

### 3.2 BaseService 核心方法（父类提供，直接用）

| 方法 | 作用 |
|------|------|
| `getPageParam()` | 从请求取 `page` / `limit`，自动校验 |
| `getPageList($model, $where, $field, $order, $append, $with, $each)` | 标准分页查询 |
| `pageQuery($model, $each)` | 分页已组装好的查询构造器 |
| `getPageViewList(...)` | 同 getPageList，但 withJoin（视图查询） |

### 3.3 Model 必须继承 `core\base\BaseModel`

**ThinkPHP 8 升级必备补丁**（BaseModel 已内置，骨架 Model 直接继承即可享受）：

| 问题 | BaseModel 解法 |
|------|----------------|
| `timestamp` 字段写入被 ThinkPHP 8 写成字符串 | `writeTransform()` / `getDateTime()` 强制写 int 时间戳 |
| 软删除字段 `delete_time = 0` 被自动格式化成 `1970-01-01 08:00:00` | `getDeleteTimeAttr` 访问器原样返回 |
| `saveAll()` 批量写入不再自动事务包裹 | 重写为事务包裹的版本 |
| ThinkPHP 8 不再消费模型的 `$json` 声明 | `getBaseOptions()` / `getQuery()` 自动翻译到查询层 |

**Model 类定义模板**：

```php
namespace addon\{key}\app\model;

use core\base\BaseModel;

class {ModulePascal} extends BaseModel
{
    protected $pk = 'id';
    protected $name = '{key}_{table_name}';  // 不带表前缀

    // ThinkPHP 8 新写法（推荐）
    protected $type = [
        'id'          => 'integer',
        'site_id'     => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];
}
```

**软删除**：引入 `use Think\model\concern\SoftDelete;`，定义 `$deleteTime = 'delete_time'` 和 `$defaultSoftDelete = 0`。

### 3.4 搜索器 / 获取器规范

**搜索器**（简化条件查询）：

```php
// 数据表字段 create_time → 搜索器方法 searchCreateTimeAttr
public function searchCreateTimeAttr($query, $value, $data)
{
    if ($value && is_array($value) && count($value) == 2) {
        $query->whereBetweenTime('create_time', $value[0], $value[1]);
    }
}

// 使用：User::withSearch(['create_time'], ['create_time' => ['2024-01-01', '2024-12-31']])->select();
```

**获取器**（格式化展示）：

```php
public function getStatusTextAttr($value, $data)
{
    return $data['status'] == 1 ? '启用' : '禁用';
}

// 使用：$model->append(['status_text'])->select();
```

### 3.5 模糊搜索必须转义通配符

```php
// 搜索器里的 like 条件
$query->where('title', 'like', '%' . $this->model->handelSpecialCharacter($params['keyword']) . '%');
```

`handelSpecialCharacter` 转义 `%` 和 `_`，防止 SQL 通配符注入。**骨架里所有模糊搜索必须调这个方法。**

### 3.6 Validate 多语言键命名

错误消息统一格式：`validate_{插件名}_{模型名}.{字段}_{验证类型}`

```php
protected $message = [
    'title.require' => 'validate_ceping_question.title_require',
];
```

自定义验证方法以 `check` 开头（`checkStatus`）。按场景组织：`addOrEdit` / `pages` / `info`。

### 3.7 路由 URL 规则

- 后端请求 URL **不带** `addon/`，如 `/adminapi/{key}/item`、`/api/{key}/item`
- 路由配置里才写完整命名空间：`'addon\{key}\app\adminapi\controller\{ModulePascal}'`
- 前端 API 请求地址同样不带 `addon/`

## 4. 生成骨架

模板目录：`assets/addon-template/`（完整镜像树，含可工作的示例条目 `Entity/item`）。

步骤：
1. 将 `assets/addon-template/` 整树复制到 `niucloud/addon/{key}/`。
2. **重命名文件/目录**（复制后执行）：
   - 所有名为 `Entity.php` 的文件 → 大驼峰 key（snake→Pascal，如 `hello_world` → `HelloWorld.php`）
   - 所有名为 `item` 的文件/目录保持不变（示例资源固定叫 item）
3. **全文替换占位符**（对所有文件内容）：

| 占位符 | 替换为 | 示例 |
|---|---|---|
| `{{KEY}}` | key 原样 | `hello_world` |
| `{{TITLE}}` | title | `抽奖小游戏` |
| `{{SHORT_TITLE}}` | short_title | `抽奖` |
| `{{DESC}}` | desc | — |
| `{{AUTHOR}}` | author | — |
| `{{TYPE}}` | type | `addon` |
| `{{SUPPORT_VERSION}}` | support_version | `1.1.7` |

注意 SQL 文件中的 `{{prefix}}` 是**框架安装时**才替换的占位符，**本步骤不要动它**。

## 5. 生成结果（30 个文件）

```
niucloud/addon/{key}/
├── Addon.php                              # 生命周期 install/uninstall/upgrade
├── info.json
├── sql/
│   ├── install.sql                        # {{prefix}} 占位符
│   └── unInstall.sql
├── package/
│   ├── composer.json
│   ├── admin-package.json
│   ├── uni-app-package.json
│   └── web-package.json
├── app/
│   ├── adminapi/{controller/HelloWorld.php, route/route.php}
│   ├── api/{controller/HelloWorld.php, route/route.php}
│   ├── dict/
│   │   ├── menu/{admin.php(平台端,必须为空数组), site.php(站点端,放示例菜单)}
│   │   └── schedule/schedule.php          # 计划任务模板
│   ├── model/HelloWorld.php               # 必须继承 core\base\BaseModel
│   ├── service/{admin,api,core}/...        # admin 继承 BaseAdminService, api 继承 BaseApiService
│   ├── validate/HelloWorld.php
│   ├── lang/{zh-cn,en}/{api,dict,validate}.php
│   ├── listener/                          # 事件监听器（*Listener.php）
│   ├── job/                                # 队列任务 / 计划任务消费类（继承 core\base\BaseJob）
│   └── event.php                          # 插件事件钩子配置
├── admin/{api/item.ts, views/item/list.vue}
├── uni-app/{api/item.ts, pages/item/list.vue}
├── web/{api/item.ts, pages/item/list.vue, pages/routes.ts}
└── package/uni-app-pages.php              # PAGE_BEGIN/END 页面声明
```

示例条目对应数据表 `{prefix}{key}`（id、site_id、title、sort、status、create_time、update_time），安装后即有可验证的完整 CRUD 闭环。

## 6. 菜单字典完整结构

```php
// app/dict/menu/site.php
return [
    [
        'menu_name'        => '插件中文名',
        'menu_key'         => '{key}',
        'menu_short_name'  => '简称',
        'parent_key'       => '',
        'parent_select_key'=> '',
        'menu_type'        => 0,          // 0=目录 1=菜单页面 2=按钮操作
        'icon'             => 'iconfont icontubiao',
        'api_url'          => '',
        'router_path'      => '',
        'view_path'        => '',
        'methods'          => '',
        'sort'             => 100,
        'status'           => 1,
        'is_show'          => 1,
        'is_blank'         => 0,
        'children'         => [
            [
                'menu_name'   => '列表页',
                'menu_key'    => '{key}_item',
                'menu_short_name' => '列表',
                'menu_type'   => 1,
                'api_url'     => '{key}/item',           // URL 不带 addon/
                'router_path' => '{key}/item/list',
                'view_path'   => 'item/list',
                'methods'     => 'get',
                'sort'        => 100,
                'status'      => 1,
                'is_show'     => 1,
                'children'    => [
                    [
                        'menu_name'   => '添加',
                        'menu_key'    => '{key}_item_add',
                        'menu_short_name' => '添加',
                        'menu_type'   => 2,
                        'api_url'     => '{key}/item',
                        'methods'     => 'post',
                        'sort'        => 0,
                        'is_show'     => 0,
                    ],
                ],
            ],
        ],
    ],
];
```

### 6.1 菜单三层

| menu_type | 含义 | 关键字段 |
|-----------|------|----------|
| 0 | 目录（不可访问，仅分组） | 只填 menu_name / menu_key / icon |
| 1 | 菜单页面（有前端页面） | router_path + view_path 必填；api_url + methods 建议填（权限控制） |
| 2 | 按钮操作（无页面，权限粒度） | api_url + methods 必填；router_path / view_path 留空；is_show = 0 |

### 6.2 替换框架已有菜单（软删除技巧）

如果插件要替换框架已有的菜单（比如插件覆盖会员菜单），在菜单条目里加：

```php
'delete' => 'member',  // member 是框架菜单 key，系统会软删除框架那个菜单
```

### 6.3 前端页面定位

`view_path` 字段 → Admin 前端文件路径：`admin/src/addon/{key}/views/{view_path}.vue`

## 7. 事件钩子（解耦神器）

### 7.1 框架预置事件点

```
SiteInit           站点初始化
AddSiteAfter       站点创建后
MemberRegister     会员注册
MemberLogin        会员登录
MemberRegisterCallback  会员注册回调
PayCreate          支付创建
PaySuccess         支付成功
RefundSuccess      退款成功
TransferSuccess    转账成功
NoticeData         消息数据
GetPosterData      获取海报数据
GetQrcodeOfChannel 获取渠道二维码
ExportData         导出数据
```

### 7.2 插件定义事件

```php
// addon/{key}/app/event.php
return [
    'bind' => [],
    'listen' => [
        'MemberRegister' => [
            'addon\{key}\app\listener\MemberRegisterListener',
        ],
    ],
    'subscribe' => [],
];
```

### 7.3 监听器

```php
// addon/{key}/app/listener/MemberRegisterListener.php
namespace addon\{key}\app\listener;

class MemberRegisterListener
{
    public function handle(array $data)
    {
        // $data 是事件传入的数据
    }
}
```

### 7.4 触发事件

```php
// 在自己的 Service 里触发
event('MemberRegister', ['member_id' => 1, 'nickname' => 'xxx']);

// 框架预置的事件会自动触发，插件只需要 listen
```

## 8. 异步任务双轨

### 8.1 消息队列（实时/延时）

**配置**：`.env` 里 `QUEUE_STATE = true`，`QUEUE_DRIVER = redis`（推荐）。

**创建任务类**：继承 `core\base\BaseJob`，实现 `doJob()`。

```php
// addon/{key}/app/job/ExampleJob.php
namespace addon\{key}\app\job;

use core\base\BaseJob;

class ExampleJob extends BaseJob
{
    protected function doJob(int $member_id, int $score)
    {
        // 业务逻辑
        return true;
    }
}
```

**推送任务**：

```php
use addon\{key}\app\job\ExampleJob;

ExampleJob::dispatch(
    ['member_id' => 1, 'score' => 10],  // $data：数组作为可变参数传给 doJob
    '',                                   // $action：空则默认调 doJob
    0,                                    // $secs：延时秒数，0 即立即
    '{key}'                               // $queue_name：队列名
);
```

**启动监听**：

```bash
cd niucloud && php think queue:listen --queue={key}
# 推荐 Supervisor 守护
```

三种驱动：`sync`（同步直接执行，调试用）/ `database` / `redis`（生产推荐）。

### 8.2 计划任务（定时）

**模板定义**：

```php
// addon/{key}/app/dict/schedule/schedule.php
return [
    [
        'key'   => '{key}_daily_report',
        'name'  => '每日报告生成',
        'desc'  => '',
        'time'  => ['type' => 'day', 'day' => 1, 'hour' => 0, 'min' => 0],
        'class' => 'addon\{key}\app\job\DailyReportJob',
        'function' => '',   // 空则调 doJob
    ],
];
```

**调度频率 type**：`min` / `hour` / `day` / `week` / `month`。

**运行**：

```bash
# 临时执行一次
php think cron:schedule

# Supervisor 守护常驻
# [program:cron-schedule]
# command=php think worker:start
# directory=/path/to/niucloud

# 或系统 crontab 每分钟触发一次入口
# * * * * * cd /path/to/niucloud && php think cron:run >> /dev/null 2>&1
```

> **修改计划任务参数后必须重启 workerman**：`php think worker:stop && php think worker:start`。

## 9. 安装与验证（指引用户完成）

骨架生成后，告知用户执行：

1. **SaaS 总后台** → 开发 → 插件/应用列表 → **安装** `{title}`（安装会自动：复制三前端源码、执行 install.sql、装载菜单与任务、调 Addon::install）。
2. 站点端使用：站点套餐需勾选该插件 → 创建/进入含该套餐的站点。
3. 菜单文件若有改动，在 **SaaS 总后台** → 开发 → 菜单 → **重置菜单**（禁止直接改 sys_menu 表）。普通业务插件重置后总后台不应出现插件菜单，菜单只出现在站点后台。
4. 访问验证：
   - 站点后台示例页 `/site/{key}/item/list`（菜单 router_path 写 `{key}/item/list`，`/site` 前缀由站点端框架自动补）；
   - 手机端页面 `/addon/{key}/pages/item/list`；
   - 电脑端路由 `/{key}/item/list`（由 web/pages/routes.ts 自动装载）。
5. **安装后必须云编译**（或本地编译 admin / uni-app / web 三个端），否则前端代码不更新。

## 10. 后续清理与扩展

- 验证通过后，示例条目（item 相关：控制器 item 路由段、admin/uni-app/web 示例页、示例表）可删除，或作为真实业务的起点保留。
- 新增真实业务模块 → 使用 **niucloud-crud-generator** 技能（基于数据表生成全套代码）。
- 需要装修组件 → 使用 **niucloud-diy-component** 技能。
- 需要事件钩子 → 在 `app/event.php` 的 `listen` 中注册并创建 `app/listener/*Listener.php`，监听器入口为 `handle()`。
- 需要异步任务 → 创建 `app/job/*Job.php`（继承 `core\base\BaseJob`），队列推送或计划任务配置。

## 11. 常见坑位速查

| # | 坑 | 解法 |
|---|-----|------|
| 1 | ThinkPHP 8 `timestamp` 字段写入变字符串 | Model 继承 `core\base\BaseModel` 即可 |
| 2 | 软删除字段 `delete_time = 0` 被格式化成 `1970-01-01` | 同上，BaseModel 已修 |
| 3 | `saveAll()` 不再自动事务 | BaseModel::saveAll 已包事务，但直接调 Model::saveAll 还是裸的 |
| 4 | 模糊搜索被 SQL 通配符注入 | `$model->handelSpecialCharacter($keyword)` 转义 `%` 和 `_` |
| 5 | 插件菜单不显示 | 站点套餐没勾选该插件 → 勾选后重建/重启站点 |
| 6 | 路由 404 | 检查路由分组名、中间件、URL 是否带 addon/（不该带） |
| 7 | `$json` 声明在 ThinkPHP 8 不生效 | 用 `protected $type = ['field' => 'array']` 新写法 |
| 8 | 修改计划任务参数没生效 | `php think worker:stop && php think worker:start` |
| 9 | 修改菜单字典后前端没变化 | 后台「开发 → 站点菜单 → 重置菜单」 |
| 10 | 表结构新增字段不显示 | 清 `runtime/` 缓存 → 刷新；不行重启服务器 |
| 11 | 云编译 Node.js 内存溢出 | 改 package.json 加 `NODE_OPTIONS=--max-old-space-size=8192` |
| 12 | 授权码/密钥错误 | 核对版本匹配，独立版/SaaS 版不能互绑 |
| 13 | 后台打不开 | MySQL sql_mode 去 `NO_AUTO_VALUE_ON_ZERO` 和 `ONLY_FULL_GROUP_BY` |

## 12. 铁律（生成时必须遵守）

- **菜单归属铁律**：业务菜单只写 `app/dict/menu/site.php`；`app/dict/menu/admin.php` 保持空数组 `return [];`。严禁把 site.php 内容对称复制到 admin.php——`adminapi/`、`service/admin/`、`admin/` 这些带 admin 名字的目录两端共用，入口归属哪一端只由菜单文件决定。
- **术语铁律**：文档、注释、提交说明中的"后台"必须写明 **SaaS 总后台（平台端 admin）** 或 **站点后台（租户端 site）**，禁止使用含义不明的"后台"。
- 不修改 `core/` 与系统 `app/` 任何文件；所有代码只在本插件目录内。
- 业务表必含 `site_id int NOT NULL DEFAULT 0`；install.sql 用 `{{prefix}}`，表名字段加反引号，InnoDB + utf8mb4，所有字段有默认值（text/longtext 除外）。
- 控制器无 Controller 后缀、模型无 Model 后缀；命名空间统一 `addon\{key}\...`。
- 响应只用 `success()/fail()`，业务消息走多语言键，不硬编码中文。
- URL 不带 `addon/`，命名空间才带 `addon\`。
- Service 必须继承对应基类（BaseAdminService / BaseApiService / BaseCoreService），不要自己 new 一个。
- 模糊搜索必须调 `handelSpecialCharacter`。
