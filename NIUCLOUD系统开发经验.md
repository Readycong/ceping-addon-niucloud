# NIUCLOUD SaaS 框架开发经验

> 基于官方文档 + 项目实际代码整理，覆盖后端（PHP / ThinkPHP 8）、前端（Admin / uni-app / Web）、插件开发全链路。
> 官方文档镜像见 [dev_doc/](./dev_doc/README.md)。

---

## 一、框架定位与架构心智

### 1.1 技术栈

| 层 | 技术 |
|----|------|
| 后端 | PHP 8 + ThinkPHP 8 + MySQL 8 + Redis + Composer |
| Admin 管理端 | Vue 3 + Vite + TypeScript + Element Plus + Tailwind + Pinia |
| uni-app 手机端 | Uni-app + Vite + TypeScript + WindiCSS + uview-plus |
| Web 端 | Nuxt.js + Vue 3 + Element Plus + Pinia |
| 异步 | Workerman + Redis 队列 / 数据库队列 |
| 定时 | 内置 Cron 调度器（PHP 实现，非系统 crontab） |

### 1.2 SaaS 多租户模型

```
Niucloud 框架（基础应用）
└── 应用（一套系统只能有一个，如商城 Shop）
    └── 站点套餐（绑定插件组合）
        └── 站点（tenant，每个站点隔离数据库 + 菜单 + 权限）
            └── 会员、订单、数据...
                ↕ 可以叠加安装「插件」（如分销、刮刮乐、会员卡）
```

**核心隔离字段：`site_id`**。几乎所有业务表都带 `site_id`，服务层基类自动从请求上下文取到当前站点 ID。

### 1.3 应用 vs 插件

| 维度 | 应用（type = app） | 插件（type = addon） |
|------|---------------------|------------------------|
| 业务体量 | 完整独立系统 | 单一功能点，和业务弱耦合 |
| 菜单层级 | 站点只有一个应用时，菜单直接铺在一级；多个应用时按应用分组 | 叠加在应用菜单树下，或出现在"应用管理 → 插件 → 打开菜单子项" |
| 典型例子 | Shop 商城 | 阿里云短信、刮刮乐、会员卡 |
| 关键差异 | 框架升级可能需要同步适配 | 独立发布，跨应用通用 |

> **经验法则**：凡是只动会员积分/余额/佣金增减的功能 → 插件；凡是需要独立后台菜单体系、独立数据模型的 → 应用。不确定时先按插件开发，后期再升级。

### 1.4 二次开发黄金原则

1. **绝不修改框架核心代码**。框架升级时会被覆盖。
2. **不要改框架已有 API**，而是在插件里加 `center_v2`、`list_custom` 这样的新接口。
3. **自定义功能不要重写框架页面**，而是软删除框架菜单，挂自己的路由到自己的页面。
4. **跨插件调用用事件钩子**，不要硬依赖。

---

## 二、插件目录结构

```
niucloud/
└── addon/
    └── hello_world/                              ← 插件根目录（标识即目录名）
        ├── Addon.php                              ← install / uninstall / upgrade 三方法
        ├── info.json                              ← 插件元信息
        ├── sql/
        │   ├── install.sql                        ← 安装时执行
        │   └── unInstall.sql                      ← 卸载时执行
        ├── package/                               ← 依赖包（自动安装）
        │   ├── composer.json
        │   ├── admin-package.json
        │   ├── uni-app-package.json
        │   └── web-package.json
        ├── app/                                   ← 后端业务代码
        │   ├── adminapi/                         ← 管理端接口
        │   │   ├── controller/...
        │   │   └── route/route.php               ← 文件名必须是 route.php
        │   ├── api/                               ← 前端接口（uni-app / web 共用）
        │   │   ├── controller/...
        │   │   └── route/route.php
        │   ├── dict/
        │   │   ├── menu/
        │   │   │   ├── admin.php                 ← 平台端菜单（仅 niucloud 框架本身需要）
        │   │   │   └── site.php                  ← 站点端菜单
        │   │   ├── schedule/schedule.php         ← 计划任务模板
        │   │   ├── notice/...                    ← 消息模板
        │   │   └── ...                            ← 其他字典
        │   ├── lang/                             ← 后端语言包（zh-cn / en）
        │   ├── listener/                         ← 事件监听器（*Listener.php）
        │   ├── model/...
        │   ├── service/
        │   │   ├── admin/...                     ← 后台服务，继承 BaseAdminService
        │   │   ├── api/...                        ← 前台服务，继承 BaseApiService
        │   │   └── core/...                       ← 公共核心服务，继承 BaseCoreService
        │   ├── job/...                            ← 队列任务 / 计划任务消费类
        │   ├── validate/...                       ← 验证器
        │   ├── upgrade/...                        ← 升级脚本
        │   └── event.php                          ← 插件事件钩子配置
        ├── admin/                                 ← Admin 前端（自动生成到 admin/src/addon/hello_world/）
        │   ├── api/...
        │   ├── lang/...
        │   └── views/...
        ├── uni-app/                               ← uni-app 前端（自动生成到 uni-app/src/addon/hello_world/）
        ├── web/                                   ← Web 前端（自动生成到 web/addon/hello_world/）
        ├── compile/                               ← 编译输出目录
        └── resource/                              ← 插件资源文件
```

### info.json 示例

```json
{
  "title": "爱测评",
  "desc": "心理测评插件",
  "key": "ceping",
  "version": "1.0.0",
  "author": "your-name",
  "type": "addon",
  "support_app": "",
  "compile": [],
  "support_version": "1.1.7"
}
```

### SQL 模板

```sql
-- install.sql，表前缀用 {{prefix}} 占位
CREATE TABLE IF NOT EXISTS `{{prefix}}ceping_questions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int unsigned NOT NULL DEFAULT 0 COMMENT '站点ID',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '题目标题',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='测评题库';
```

---

## 三、后端四层架构

### 3.1 四层职责

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

### 3.2 Controller 规范

**基类**：管理端 `BaseAdminController`，前台 `BaseApiController`（都在 `core\base`）。

**命名**：直接用业务名，**不加 Controller 后缀**。按功能分目录。

**路由**：必须放在插件名分组下，避免冲突。文件名固定 `route.php`。

```php
// app/adminapi/route/route.php
use think\facade\Route;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminLog;

Route::group('ceping', function () {
    Route::get('question', 'addon\ceping\app\adminapi\controller\Question@pages');
    Route::get('question/:id', 'addon\ceping\app\adminapi\controller\Question@info');
    Route::post('question', 'addon\ceping\app\adminapi\controller\Question@add');
    Route::put('question/:id', 'addon\ceping\app\adminapi\controller\Question@edit');
    Route::delete('question/:id', 'addon\ceping\app\adminapi\controller\Question@del');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
```

> **请求 URL 不带 `addon/`**。后端 `adminapi/ceping/question`，前台 `api/ceping/question`。路由配置里才写 `addon\ceping` 命名空间。

**参数获取**：统一 `$this->request->params([['字段', 默认值], ...])`。

```php
public function pages()
{
    $params = $this->request->params([
        ['keyword', ''],
        ['status', ''],
        ['page', 1],
        ['limit', 15],
    ]);
    $this->validate($params, 'addon\ceping\app\validate\Question.pages');
    return success((new QuestionAdminService())->getPageList($params));
}
```

**响应**：`success($msg, $data)` / `fail($msg, $data)`。

**方法命名**：`pages()` / `lists()` / `info($id)` / `add()` / `edit($id)` / `del($id)` / 其他用动词如 `config()`、`apply()`。

### 3.3 Service 规范

**三层基类**：

| 基类 | 路径 | 适用场景 | 自动注入 |
|------|------|----------|----------|
| `BaseAdminService` | `core\base\BaseAdminService` | 后台管理逻辑 | `site_id` / `username` / `uid` / `app_type` |
| `BaseApiService` | `core\base\BaseApiService` | 前端用户逻辑 | `site_id` / `member_id` / `channel` |
| `BaseCoreService` | `core\base\BaseCoreService` | admin 和 api 都要用的公共逻辑 | 无请求上下文，自己取 |

**BaseService 提供的核心方法**（父类）：

| 方法 | 作用 |
|------|------|
| `getPageParam()` | 从请求取 `page` / `limit`，自动校验 |
| `getPageList($model, $where, $field, $order, $append, $with, $each)` | 标准分页查询 |
| `pageQuery($model, $each)` | 分页已组装好的查询构造器 |
| `getPageViewList(...)` | 同 getPageList，但 `withJoin`（视图查询） |

**Service 类模板**：

```php
namespace addon\ceping\app\service\admin;

use addon\ceping\app\model\Question;
use core\base\BaseAdminService;

class QuestionAdminService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Question();
    }

    public function getPageList(array $params)
    {
        $where = [['site_id', '=', $this->site_id]];
        if ($params['keyword'] !== '') {
            $where[] = ['title', 'like', '%' . $this->model->handelSpecialCharacter($params['keyword']) . '%'];
        }
        return $this->getPageList(
            $this->model,
            $where,
            '*',
            'id desc',
        );
    }

    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        return $this->model->create($data)->id;
    }

    public function edit(int $id, array $data) { ... }
    public function info(int $id) { ... }
    public function del(int $id) { ... }
}
```

> **注意 `handelSpecialCharacter`**：模糊搜索关键字前记得调模型这个方法转义 `%` 和 `_`，防止 SQL 通配符注入。

### 3.4 Model 规范

**基类**：`core\base\BaseModel`（继承 ThinkPHP `Model`）。

**类定义**：

```php
namespace addon\ceping\app\model;

use core\base\BaseModel;

class Question extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'ceping_questions';  // 不带表前缀

    // ThinkPHP 8 新写法（推荐）
    protected $type = [
        'id'          => 'integer',
        'site_id'     => 'integer',
        'options'     => 'array',    // 自动 JSON 编解码
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    // 旧写法兼容（BaseModel 会自动翻译成 type）
    // protected $json = ['options'];
    // protected $jsonAssoc = true;
}
```

**BaseModel 关键补丁**（ThinkPHP 8 升级必备）：

| 问题 | BaseModel 解法 |
|------|----------------|
| `timestamp` 字段写入被 ThinkPHP 8 写成字符串 | `writeTransform()` / `getDateTime()` 强制写 int 时间戳 |
| 软删除字段 `delete_time = 0` 被自动格式化成 `1970-01-01 08:00:00` | `getDeleteTimeAttr` 访问器原样返回 |
| `saveAll()` 批量写入不再自动事务包裹 | 重写为事务包裹的版本 |
| ThinkPHP 8 不再消费模型的 `$json` 声明 | `getBaseOptions()` / `getQuery()` 自动翻译到查询层 |

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

// 使用：$user->status_text，或 append 追加到查询结果中
// $model->field('id,status')->append(['status_text'])->select();
```

**软删除**：引入 `use Think\model\concern\SoftDelete;`，定义 `$deleteTime` 和 `$defaultSoftDelete`。

### 3.5 Validate 规范

```php
namespace addon\ceping\app\validate;

use think\Validate;

class Question extends Validate
{
    protected $rule = [
        'title'       => 'require',
        'status'      => 'in:0,1',
    ];

    protected $message = [
        'title.require' => 'validate_ceping_question.title_require',
    ];

    protected $scene = [
        'addOrEdit' => ['title', 'status'],
        'pages'     => ['keyword', 'status'],
    ];
}
```

- 错误消息用多语言键名（`validate_插件名_模型名.字段_验证类型`）。
- 自定义验证方法以 `check` 开头（`checkStatus`）。
- 按场景组织：`addOrEdit` / `pages` / `info`。

---

## 四、数据库与命名规范

- **表名**：小写下划线，`{{prefix}}ceping_questions`。
- **字段名**：小写下划线，禁止拼音/缩写。
- **模型类**：大驼峰，`Question`（不叫 QuestionModel）。
- **Service 类**：大驼峰 + `Service` 后缀，`QuestionAdminService` / `QuestionApiService`。
- **Controller 类**：大驼峰，**不加 Controller**，`Question`。
- **Validate 类**：大驼峰，**不加 Validate**，`Question`。
- **搜索器**：`searchXxxAttr`（`xxx` 对应表字段驼峰化）。
- **获取器**：`getXxxAttr`（同上）。

---

## 五、菜单与权限

### 5.1 菜单字典结构

```php
// app/dict/menu/site.php
return [
    [
        'menu_name'        => '爱测评',
        'menu_key'         => 'ceping',
        'menu_short_name'  => '测评',
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
        'children'         => [
            [
                'menu_name'   => '题库管理',
                'menu_key'    => 'ceping_question',
                'menu_short_name' => '题库',
                'menu_type'   => 1,
                'icon'        => '',
                'api_url'     => 'ceping/question',
                'router_path' => 'ceping/question/list',
                'view_path'   => 'question/list',
                'methods'     => 'get',
                'sort'        => 100,
                'status'      => 1,
                'is_show'     => 1,
                'children'    => [
                    [
                        'menu_name'   => '添加题目',
                        'menu_key'    => 'ceping_question_add',
                        'menu_short_name' => '添加',
                        'menu_type'   => 2,
                        'api_url'     => 'ceping/question',
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

### 5.2 菜单三层

| menu_type | 含义 | 关键字段 |
|-----------|------|----------|
| 0 | 目录（不可访问，仅分组） | 只填 menu_name / menu_key / icon |
| 1 | 菜单页面（有前端页面） | router_path + view_path 必填；api_url + methods 建议填（权限控制） |
| 2 | 按钮操作（无页面，权限粒度） | api_url + methods 必填；router_path / view_path 留空；is_show = 0 |

### 5.3 前端页面定位

`view_path` 字段 → Admin 前端文件路径：
```
admin/src/addon/ceping/views/{view_path}.vue
```

例如 `view_path = question/list` → `admin/src/addon/ceping/views/question/list.vue`

### 5.4 重要操作流程

1. **插件安装** → 系统自动装载菜单字典到 `sys_menu` 表。
2. **修改菜单字典后**，要到「开发 → 站点菜单 → 重置菜单」才能生效。
3. **菜单不显示**？99% 是因为没在站点套餐里勾选这个插件。站点套餐 → 编辑 → 勾选 → 保存 → 新建/重建站点。
4. **替换框架菜单**：在插件菜单里加 `'delete' => 'member'`（member 是框架菜单 key），系统会软删除框架那个菜单，再挂你的新菜单上去。

---

## 六、事件钩子（解耦神器）

### 6.1 框架已预置的事件点

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

### 6.2 插件定义事件

```php
// addon/ceping/app/event.php
return [
    'bind' => [],
    'listen' => [
        'MemberRegister' => [
            'addon\ceping\app\listener\MemberRegisterListener',
        ],
    ],
    'subscribe' => [],
];
```

### 6.3 监听器

```php
// addon/ceping/app/listener/MemberRegisterListener.php
namespace addon\ceping\app\listener;

class MemberRegisterListener
{
    public function handle(array $data)
    {
        // $data 是会员注册时传入的数据
        // 做自己的逻辑
    }
}
```

### 6.4 触发事件

```php
// 在自己的 Service 里触发
event('MemberRegister', ['member_id' => 1, 'nickname' => 'xxx']);

// 框架预置的事件会自动触发，插件只需要 listen
```

---

## 七、异步任务

### 7.1 消息队列（实时/延时）

**配置**：`.env` 里 `QUEUE_STATE = true`，`QUEUE_DRIVER = redis`（推荐）。

**创建任务类**：继承 `core\base\BaseJob`，实现 `doJob()`。

```php
// addon/ceping/app/job/ScoreCalcJob.php
namespace addon\ceping\app\job;

use core\base\BaseJob;

class ScoreCalcJob extends BaseJob
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
use addon\ceping\app\job\ScoreCalcJob;

ScoreCalcJob::dispatch(
    ['member_id' => 1, 'score' => 10],  // $data：数组作为可变参数传给 doJob
    '',                                   // $action：空则默认调 doJob
    0,                                    // $secs：延时秒数，0 即立即
    'ceping'                              // $queue_name：队列名
);
```

**启动监听**：

```bash
cd niucloud && php think queue:listen --queue=ceping
# 推荐 Supervisor 守护
```

三种驱动：`sync`（同步直接执行，调试用）/ `database` / `redis`（生产推荐）。

### 7.2 计划任务（定时）

**模板定义**：

```php
// addon/ceping/app/dict/schedule/schedule.php
return [
    [
        'key'   => 'ceping_daily_report',
        'name'  => '每日测评报告生成',
        'desc'  => '',
        'time'  => ['type' => 'day', 'day' => 1, 'hour' => 0, 'min' => 0],
        'class' => 'addon\ceping\app\job\DailyReportJob',
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
# command=php think cron:schedule
# directory=/path/to/niucloud

# 或系统 crontab 每分钟触发一次入口
# * * * * * cd /path/to/niucloud && php think cron:run >> /dev/null 2>&1
```

> **修改计划任务参数后必须重启 workerman**：`php think worker:stop && php think worker:start`。

---

## 八、数据字典

字典文件统一放 `app/dict/` 或 `addon/xxx/app/dict/`，用于常量、枚举、配置。

| 字典类型 | 路径 | 命名 |
|----------|------|------|
| 菜单 | `dict/menu/{admin,site}.php` | 文件即类型 |
| 计划任务 | `dict/schedule/schedule.php` | 文件即类型 |
| 消息模板 | `dict/notice/{type}.php` | 按渠道 |
| 自定义字典 | `dict/xxx/{Xxx}Dict.php` | 大驼峰 + Dict 后缀 |

---

## 九、前端开发要点

### 9.1 Admin（Vue3 + TypeScript + Element Plus）

- API 定义放 `addon/xxx/admin/api/`，请求地址不带 `addon/`。
- 页面放 `addon/xxx/admin/views/`，路径对应菜单 `view_path`。
- 样式优先 Tailwind，其次 SCSS（入口在 `admin/src/styles/common.scss`）。
- 语言包按路由路径命名：路由 `ceping/question` → 语言包 `ceping.question.json`。公共的放 `common.json`。
- API 请求工具 `admin/src/utils/request.ts`，支持 `showErrorMessage` / `showSuccessMessage` 等选项。

### 9.2 uni-app（跨平台）

- 页面路径必须以 `app/pages/` 开头（pages.json 配置）。
- 导航栏标题用语言包键名 `"%pages.xxx.title%"`，禁止硬编码。
- 需要登录的页面声明 `"needLogin": true`。
- 组件 props 下传数据、emit 上抛事件；样式必须 scoped。
- WindiCSS 优先，能用原子类就不写自定义 CSS。
- API 定义放 `addon/xxx/uni-app/api/`。

### 9.3 Web（Nuxt.js）

- 结构和 Admin 类似，API 放 `addon/xxx/web/api/`，页面放 `addon/xxx/web/pages/`。

### 9.4 DIY 装修（自定义组件）

框架提供了 DIY 页面装修能力，插件可以注册自定义组件：

1. 后端：在 dict 里声明组件类型、配置项、输出格式。
2. Admin 端：开发组件编辑器。
3. uni-app 端：开发组件渲染器。

详见 [dev_doc/pluginDev/diyComponentDevGuide.md](./dev_doc/pluginDev/diyComponentDevGuide.md)。

---

## 十、开发工作流

```
1. 数据库设计 → create install.sql / unInstall.sql
2. 新建插件 → 目录骨架 + Addon.php + info.json
3. 写 Model → 表映射 + type + 搜索器 + 获取器
4. 写 Validate → 场景规则 + 多语言键
5. 写 Service → 继承对应基类 + 标准方法 + site_id 注入
6. 写 Controller → 路由分组 + 中间件 + params + validate + 响应
7. 写路由 route.php → 插件名分组 + RESTful 风格 + 中间件
8. 写菜单 dict/menu/site.php → 目录/页面/按钮三层
9. 写前端 API → 对应后端路由地址
10. 写前端页面 → 对应 view_path
11. 写语言包 → Validate 错误消息 + 前端展示文本
12. 安装插件 → 重置菜单 → 站点套餐勾选 → 验证
13. 事件钩子 / 消息队列 / 计划任务（按需）
```

---

## 十一、常见坑位

| # | 坑 | 解法 |
|---|-----|------|
| 1 | ThinkPHP 8 `timestamp` 字段写入变字符串 | 直接用项目里的 `BaseModel`，它已补丁 `writeTransform` 和 `getDateTime` |
| 2 | 软删除字段 `delete_time = 0` 被格式化成 `1970-01-01` | 同样是 `BaseModel` 已解决（`getDeleteTimeAttr`） |
| 3 | `saveAll()` 不再自动事务 | `BaseModel::saveAll` 已包事务，但你直接调 `Model::saveAll` 还是裸的 |
| 4 | 模糊搜索被 SQL 通配符注入 | `$model->handelSpecialCharacter($keyword)` 转义 `%` 和 `_` |
| 5 | 插件菜单不显示 | 站点套餐没勾选该插件 → 勾选后重建/重启站点 |
| 6 | 路由 404 | 检查 `app/adminapi/route/route.php` 里插件名分组、中间件是否正确 |
| 7 | `$json` 声明在 ThinkPHP 8 不生效 | 用 `protected $type = ['field' => 'array']` 新写法；或等 `BaseModel::getBaseOptions` 自动翻译 |
| 8 | 修改计划任务参数没生效 | 必须 `php think worker:stop && php think worker:start` 重启 |
| 9 | 修改菜单字典后前端没变化 | 后台「开发 → 站点菜单 → 重置菜单」 |
| 10 | 安装插件时 npm/composer 检测不可用 | 手动把依赖放进 `package/` 目录，或跳过自动安装步骤 |
| 11 | 前端文件明明在 `views/question/list.vue` 却找不到 | 检查菜单 `view_path` 是否写的是 `question/list`（不带 `.vue`） |
| 12 | 搜索器调用 `withSearch(['create_time'], [...])` 没触发 | 字段名必须是下划线格式 `create_time`，驼峰 `createTime` 不会匹配 |

---

## 十二、二开环境搭建

官方推荐的开发环境：

| 组件 | 版本 | 说明 |
|------|------|------|
| PHP | 8.0+ | 必选 |
| MySQL | 8.0+ | 字符集 utf8mb4 |
| Redis | 5.0+ | 队列和缓存 |
| Nginx | 最新 | 伪静态规则指向 `public/router.php` |
| Composer | 最新 | 依赖管理 |
| Node.js | 18+ | 前端构建 |

本地可使用 PHPStudy / Docker / 宝塔。Nginx 伪静态：

```nginx
location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.php?s=$1 last;
    }
}
```

开发模式下，Admin 前端：`cd admin && npm run dev`；uni-app：用 HBuilderX 或 `npm run dev:h5`。

---

## 参考文档

- 官方文档本地镜像：[dev_doc/README.md](./dev_doc/README.md)
- 插件目录结构：[dev_doc/pluginDev/pluginDirectoryStructure.md](./dev_doc/pluginDev/pluginDirectoryStructure.md)
- 服务层规范：[dev_doc/pluginDev/serviceDevSpec.md](./dev_doc/pluginDev/serviceDevSpec.md)
- 模型层开发：[dev_doc/pluginDev/modelBaseConfig.md](./dev_doc/pluginDev/modelBaseConfig.md)
- 事件钩子：[dev_doc/pluginDev/eventHookDevGuide.md](./dev_doc/pluginDev/eventHookDevGuide.md)
- 消息队列：[dev_doc/pluginDev/messageQueue.md](./dev_doc/pluginDev/messageQueue.md)
- 计划任务：[dev_doc/pluginDev/scheduleDev.md](./dev_doc/pluginDev/scheduleDev.md)
- 菜单开发：[dev_doc/pluginDev/menuDev.md](./dev_doc/pluginDev/menuDev.md)
