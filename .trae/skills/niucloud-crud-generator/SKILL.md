---
name: niucloud-crud-generator
description: Generate full-stack CRUD code from a table in an existing niucloud addon — model, validate, services, controllers, routes and Vue pages. Use when building CRUD from a database table.
---

# Niucloud CRUD Generator

基于数据表，为**已存在的 niucloud 插件**生成一套可工作的全栈 CRUD 代码。只生成在插件目录内，不改框架。

## 1. 收集参数

| 参数 | 规则 |
|---|---|
| key | 目标插件 key（`niucloud/addon/{key}/` 必须存在）。多个插件时问用户 |
| table | 业务表名（含/不含全局前缀均可），通常已声明在插件 `sql/install.sql` |
| module | 业务模块名，snake_case。默认推导：表名去全局前缀后，若以 `{key}_` 开头则去掉前缀，如 key=cms、表 `cms_article` → module=`article`。推导不出或不唯一时问用户 |

命名转换：
- `{{MODULE_PASCAL}}` = module 大驼峰：`article` → `Article`
- `{{TABLE}}` = 不含全局前缀的完整表名：`cms_article`
- `{{TABLE_PASCAL}}` = 表名大驼峰：`CmsArticle`

## 2. 分析表结构（核心步骤）

**首选**解析插件 `niucloud/addon/{key}/sql/install.sql` 中该表的 `CREATE TABLE`（字段名、类型长度、NULL、默认值、COMMENT）。
install.sql 没有该表时，请用户提供 `SHOW CREATE TABLE` 结果或建表语句后粘贴。

对每个字段提取：`字段名`、`类型`、`长度`、`是否 NOT NULL`、`默认值`、`COMMENT`（作为表单/列标签）。

### 2.1 系统字段（不进表单、不生成业务规则）

`site_id`（多租户自动注入）、`create_time`、`update_time`（服务层自动写 time()）、`delete_time`（仅启用软删除）。

### 2.2 字段 → 模型/验证映射

| 列类型 | 验证规则 | 模型处理 | 表单组件 |
|---|---|---|---|
| varchar(n) NOT NULL 无默认值 | `require\|max:n` | — | el-input |
| varchar(n) 其他 | `max:n` | — | el-input |
| char(n) | `max:n` | — | el-input |
| text / mediumtext / longtext | 无 | 控制器 params 第三参 `false` | el-input type=textarea（富文本用项目富文本组件） |
| int / smallint / bigint | `number` | — | el-input-number |
| tinyint（status 类） | `number\|between:0,1` | 搜索器精确匹配 | el-switch / el-radio |
| decimal(m,d) / float | `float` / `egt:0` | — | el-input-number :precision |
| json | — | `$type = ['field' => 'array']` | 不生成，人工处理 |
| 命名 `*_time` 的 int | — | `$type = ['field' => 'timestamp']` | el-date-picker |

### 2.3 划分字段集合

- **搜索字段 search**：默认 = 名称类字段（title/name，模糊）+ status 类字段（精确）+ create_time（范围）；仅为实际需要的筛选生成。
- **列表字段 list**：除长文本/content/json 外的业务字段 + create_time；用于表格列与 `field`。
- **表单字段 form**：除系统字段外全部业务字段。
- **排序 order**：有 sort 字段用 `sort desc, {pk} desc`，否则 `create_time desc, {pk} desc`。
- 主键 `pk`：默认 `id`，复合主键按实际。
- `COMMENT`：取表注释，没有用模块中文名。

## 3. 生成后端文件

模板在 `assets/tpl/`。按下列路径写入（已存在则跳过并告知，不覆盖用户代码）：

| 模板 | 目标路径（相对 `niucloud/addon/{key}/`） |
|---|---|
| model.php | `app/model/{{TABLE_PASCAL}}.php`（模型类名=表名大驼峰，保留业务子目录时跟随） |
| validate.php | `app/validate/{{MODULE}}/{{MODULE_PASCAL}}.php` |
| service-admin.php | `app/service/admin/{{MODULE}}/{{MODULE_PASCAL}}Service.php` |
| service-api.php | `app/service/api/{{MODULE}}/{{MODULE_PASCAL}}Service.php` |
| controller-admin.php | `app/adminapi/controller/{{MODULE}}/{{MODULE_PASCAL}}.php` |
| controller-api.php | `app/api/controller/{{MODULE}}/{{MODULE_PASCAL}}.php` |

### 3.1 生成代码必须遵守的后端规范

#### Model（必须继承 `core\base\BaseModel`）

```php
namespace addon\{key}\app\model;

use core\base\BaseModel;

class {{TABLE_PASCAL}} extends BaseModel
{
    protected $pk = 'id';
    protected $name = '{{TABLE}}';

    // ThinkPHP 8 新写法
    protected $type = [
        'id'          => 'integer',
        'site_id'     => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
        // 其他业务字段按需填
    ];

    // 搜索器（每个搜索字段一个）
    public function searchCreateTimeAttr($query, $value, $data) { ... }
    public function searchTitleAttr($query, $value, $data) { ... }
    public function searchStatusAttr($query, $value, $data) { ... }

    // 获取器（每个需要格式化展示的字段一个）
    public function getStatusTextAttr($value, $data) { ... }
}
```

#### Service（admin 继承 BaseAdminService，api 继承 BaseApiService）

```php
namespace addon\{key}\app\service\admin;

use addon\{key}\app\model\{{TABLE_PASCAL}};
use core\base\BaseAdminService;

class {{MODULE_PASCAL}}Service extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new {{TABLE_PASCAL}}();
    }

    public function getPageList(array $params)
    {
        $where = [['site_id', '=', $this->site_id]];
        // 模糊搜索必须调 handelSpecialCharacter
        if ($params['keyword'] !== '') {
            $where[] = ['title', 'like', '%' . $this->model->handelSpecialCharacter($params['keyword']) . '%'];
        }
        return $this->getPageList(
            $this->model,
            $where,
            '*',
            'create_time desc',
        );
    }

    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;  // 必须注入 site_id
        return $this->model->create($data)->id;
    }
}
```

**BaseService 父类提供的核心方法**：
- `getPageParam()` — 取 `page` / `limit`
- `getPageList($model, $where, $field, $order, $append, $with, $each)` — 标准分页
- `pageQuery($model, $each)` — 已组装查询构造器的分页
- `getPageViewList(...)` — withJoin 视图查询

#### Controller（无 Controller 后缀）

```php
namespace addon\{key}\app\adminapi\controller;

class {{MODULE_PASCAL}} extends BaseAdminController
{
    public function pages()
    {
        $params = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 15],
        ]);
        $this->validate($params, 'addon\{key}\app\validate\{{MODULE}}/{{MODULE_PASCAL}}.pages');
        return success((new \addon\{key}\app\service\admin\{{MODULE_PASCAL}}Service())->getPageList($params));
    }
}
```

**路由 URL 规则**：URL 不带 `addon/`，如 `/adminapi/{key}/item`；命名空间才写 `addon\{key}\...`。

#### Validate（多语言键命名）

```php
namespace addon\{key}\app\validate;

use think\Validate;

class {{MODULE_PASCAL}} extends Validate
{
    protected $rule = [
        'title'  => 'require|max:255',
        'status' => 'in:0,1',
    ];

    protected $message = [
        'title.require' => 'validate_{{key}}_{{module}}.title_require',
    ];

    protected $scene = [
        'addOrEdit' => ['title', 'status'],
        'pages'     => ['keyword', 'status'],
    ];
}
```

写入后：
1. 替换全部占位符；
2. **删除模板中所有不适用的可选块与注释示例**（如没有 status 字段则删对应搜索器/规则/表单）；
3. `withSearch([...])` 的字段名数组必须与模型中实际生成的搜索器一一对应。

### 合并路由（编辑，不覆盖）

把 `assets/tpl/route-append.php` 的 5 行**追加进已有** `app/adminapi/route/route.php` 的 `Route::group('{key}', ...)` 闭包内；前台只读两行追加进 `app/api/route/route.php`。不新建 group。

## 4. 生成前端文件

| 模板 | 目标路径 |
|---|---|
| admin-api.ts | `admin/api/{{MODULE}}.ts` |
| admin-list.vue | `admin/views/{{MODULE}}/list.vue` |
| admin-edit.vue | `admin/views/{{MODULE}}/edit.vue` |
| uniapp-api.ts | `uni-app/api/{{MODULE}}.ts` |
| web-api.ts | `web/api/{{MODULE}}.ts` |

Vue 模板中需手工展开的占位：
- `{{TABLE_COLUMNS}}`：每个列表字段一个 `<el-table-column prop="字段" :label="COMMENT标签" />`；
- `{{SEARCH_FORM_ITEMS}}` / `{{SEARCH_FORM_STATE}}` / `{{SEARCH_RESET}}`：搜索字段对应表单项、初始值、重置赋值；
- `{{FORM_ITEMS}}` / `{{FORM_STATE}}` / `{{FORM_RULES}}`：表单字段的 el-* 组件、reactive 初始值（用列默认值）、校验规则（require 与后端一致）；
- 图片 URL 字段：优先复用项目已有上传组件（先搜索 admin/src 下 upload/image 组件），没有则用 el-input 占位并注明。

### 4.1 追加菜单（只写 site.php）

把 `assets/tpl/menu-append.php` 片段**只**追加到 `app/dict/menu/site.php` 父菜单 children。业务 CRUD 属于站点后台（app_type=site）；**禁止**同步写入 `admin.php`——`app/adminapi/`、`app/service/admin/` 名字虽带 admin，但平台端与站点端共用这些目录，功能归属哪一端只由菜单文件决定（普通插件 admin.php 必须为空）。

菜单完整字段见 [niucloud-addon-scaffold SKILL.md](#6-菜单字典完整结构)。

## 5. 占位符总表

`{{KEY}}` `{{MODULE}}` `{{MODULE_PASCAL}}` `{{TABLE}}` `{{TABLE_PASCAL}}` `{{PK}}` `{{COMMENT}}` `{{NAME_FIELD}}` `{{STATUS_FIELD}}` `{{LIST_FIELDS}}` `{{ALL_FIELDS}}` `{{SEARCH_FIELDS}}` `{{ORDER}}` `{{SEARCH_PARAMS}}` `{{FORM_PARAMS}}` `{{MENU_NAME}}` `{{MENU_SHORT_NAME}}` 及上述 Vue 片段占位。

## 6. 收尾指引（告知用户）

1. 后端 PHP 在 `niucloud/addon` 下被直接加载，改动即时生效；前端源码需重新安装插件复制到 admin/uni-app/web，或在分离构建流程中重新打包。
2. 新增表 → 确保 `sql/install.sql` 已包含（新装站点用）；已装站点需手工建表或走升级包 `app/upgrade/v{版本}/upgrade.sql`（**升级 SQL 不带 前缀占位符**）。
3. 菜单变更 → **SaaS 总后台**「开发 → 菜单 → 重置菜单」，禁止改 sys_menu 表；业务菜单只进 site.php、admin.php 保持不动；站点端还需套餐授权。
4. 验证路由：站点后台页 `/site/{key}/{module}/list`（菜单 router_path 不带 `/site`，框架按 app_type=site 自动补前缀）；接口 `/adminapi/{key}/{module}` 由平台端与站点端共用。
5. **生成后必须云编译**（或本地编译三个前端），否则前端代码不更新。

## 7. 常见坑位

| # | 坑 | 解法 |
|---|-----|------|
| 1 | ThinkPHP 8 `timestamp` 字段写入变字符串 | Model 继承 `core\base\BaseModel` 即可 |
| 2 | 软删除 `delete_time = 0` 被格式化成 `1970-01-01` | 同上 |
| 3 | 模糊搜索被 SQL 通配符注入 | `$this->model->handelSpecialCharacter($keyword)` |
| 4 | 插件菜单不显示 | 站点套餐没勾选该插件 |
| 5 | 路由 404 | 检查路由分组名、URL 是否带 addon/（不该带） |
| 6 | `$json` 声明在 ThinkPHP 8 不生效 | 用 `$type = ['field' => 'array']` 新写法 |
| 7 | SaveAll 不再自动事务 | BaseModel 已包，但直接调 Model 还是裸的 |
| 8 | 表结构新增字段不显示 | 清 `runtime/` 缓存 |

## 8. 铁律

- **菜单只写 `app/dict/menu/site.php`，`admin.php` 保持空数组**；不要因为生成的是 adminapi 控制器与 service/admin 服务就误写平台端菜单——带 admin 名字的目录两端共用，端的归属只由菜单文件决定。
- Model 必须继承 `core\base\BaseModel`（ThinkPHP 8 升级补丁全部内置）。
- Service 必须继承 `BaseAdminService` / `BaseApiService`，不要自己 new。
- 查询必带 `site_id` 条件，写入必注入 `site_id`；系统字段不在控制器 params 中接收。
- 控制器无 Controller 后缀、模型无 Model 后缀；响应只用 `success()/fail()`，消息走多语言键。
- URL 不带 `addon/`，命名空间才带 `addon\`。
- 模糊搜索必须调 `handelSpecialCharacter`。
- 不修改 `core/` 与系统 `app/`。
