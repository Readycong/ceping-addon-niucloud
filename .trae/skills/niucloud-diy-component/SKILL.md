---
name: niucloud-diy-component
description: Create the three parts of a niucloud DIY component in an existing addon — PHP dictionary entry, admin editor Vue and uni-app renderer. Use when adding a DIY or decoration component.
---

# Niucloud DIY Component

为**已存在的 niucloud 插件**创建一个 DIY 装修组件。一个可工作的 DIY 组件必须同时具备三处代码（"三件套"），命名互相对应。

## 1. 收集参数

| 参数 | 规则 |
|---|---|
| key | 目标插件 key（`niucloud/addon/{key}/` 必须存在） |
| ComponentKey | 组件大驼峰 key，全工程唯一，如 `ArticleList`。dict 数组键与装修 JSON 中的 componentName 都用它 |
| path | kebab-case，与 ComponentKey 对应：`ArticleList` → `article-list`。**三处共用**：dict 的 `path`、admin 编辑组件文件名 `edit-{path}.vue`、uni-app 渲染组件目录 `{path}/` |
| title | 组件中文名（组件库面板显示） |
| category | 面板分类，默认 `BASIC`；插件自有分类需提供大写分类 key |

先向用户确认业务字段：组件需要哪些可配置数据（数量、数据源、列表项、链接等），再定 value 结构。

## 2. 生成三件套

模板在 `assets/tpl/`。目标路径（相对插件源目录 `niucloud/addon/{key}/`）：

| 模板 | 目标路径 | 操作 |
|---|---|---|
| components-entry.php | `app/dict/diy/components.php` | **合并**，不覆盖 |
| admin-edit.vue | `admin/views/diy/components/edit-{path}.vue` | 新建 |
| uniapp-render.vue | `uni-app/components/diy/{path}/index.vue` | 新建 |

### 2.1 合并字典 components.php

- 文件不存在 → 新建 `return ['{CATEGORY}' => ['title' => 分类名, 'list' => [条目]]]`。
- 文件已存在 → 把条目放入对应分类的 `list`；分类不存在则新增分类。
- 条目内 `template`（公共样式默认值）与 `value`（业务数据初始值）分开；用不到的公共样式键也保留，由框架统一消费。

### 2.2 admin 编辑组件约定

- 两个面板区块：`v-show="diyStore.editTab == 'content'"`（内容）与 `'style'`（样式）。
- 业务属性直接绑定 `diyStore.editComponent.字段`；样式区块末尾必须保留 `<slot name="style"></slot>`（框架注入公共属性面板）。
- `diyStore.editComponent.ignore = []`：要屏蔽的公共属性 key 放这里。
- `diyStore.editComponent.verify = (index) => { return {code, message} }`：保存前校验，`code:false` 阻止保存。
- 链接选择统一用系统组件 `<diy-link v-model="字段" />`；列表排序用 sortablejs；列表项 id 用 `diyStore.generateRandom()` 补齐。

### 2.3 uni-app 渲染组件约定

- `defineProps(['component', 'index'])`；装修态（`diyStore.mode == 'decorate'`）取 `diyStore.value[index]`，渲染态取 `props.component`。
- 容器背景/渐变/背景图/圆角读公共样式字段（见模板 `wrapCss`），尺寸 px 转 rpx 时乘 2。
- 点击事件装修态必须直接 return：`if (diyStore.mode == 'decorate') return`；跳转走 `diyStore.toRedirect(link)`。
- 无需手动注册：`diy-group` 按目录自动扫描 `components/diy/{path}/index.vue`。

## 3. 公共样式字段（template）

| 字段 | 含义 |
|---|---|
| textColor | 业务文字色（组件自行使用） |
| pageStartBgColor / pageEndBgColor / pageGradientAngle | 页面背景渐变 |
| componentStartBgColor / componentEndBgColor / componentGradientAngle | 组件容器背景渐变 |
| componentBgUrl / componentBgAlpha | 容器背景图与遮罩透明度（0-10，消费时 /10） |
| topRounded / bottomRounded | 容器上下圆角 |
| elementBgColor / topElementRounded / bottomElementRounded | 内部元素背景与圆角 |
| marginTop / marginBottom / marginBoth | 组件外边距 |

只在渲染组件中实现组件级字段；页面背景由框架处理。

## 4. 数据源后端（如果组件需要动态数据）

如果 DIY 组件需要从后端拉数据（如文章列表、商品列表），按以下规范创建 API：

### 4.1 Controller（api 端）

```php
// addon/{key}/app/api/controller/DiyData.php
namespace addon\{key}\app\api\controller;

class DiyData extends BaseApiController
{
    public function articleList()
    {
        $params = $this->request->params([
            ['limit', 10],
        ]);
        return success((new \addon\{key}\app\service\api\DiyDataService())->getArticleList($params));
    }
}
```

**注意**：URL 不带 `addon/`，如 `/api/{key}/diy_data/article_list`；命名空间才写 `addon\{key}\...`。

### 4.2 Service（api 继承 BaseApiService）

```php
namespace addon\{key}\app\service\api;

use addon\{key}\app\model\Article;
use core\base\BaseApiService;

class DiyDataService extends BaseApiService
{
    public function getArticleList(array $params)
    {
        $where = [['site_id', '=', $this->site_id]];
        $limit = $params['limit'];
        return Article::where($where)->limit($limit)->select()->toArray();
    }
}
```

### 4.3 Model（继承 core\base\BaseModel）

同 [niucloud-addon-scaffold SKILL.md](#33-model-必须继承-corebasemodel) 规范。

### 4.4 前端 API 调用

在 uni-app 渲染组件里，用项目统一的请求工具：

```javascript
import request from '@/utils/request'

async function fetchData() {
    const res = await request('/api/{key}/diy_data/article_list', { limit: props.component.value.count })
    return res.data
}
```

## 5. 收尾指引（告知用户）

1. dict 变更后，站点后台装修页组件库即可读取（PHP 即时生效）；前端两端需重新安装插件复制代码或重新打包。
2. 在装修编辑器拖入组件 → 改属性 → 保存；已发布页面重新发布才生效。
3. 已发布的旧页面不含新组件，属正常。
4. **新增数据接口后必须云编译**（或本地编译三个前端），否则前端代码不更新。

## 6. 与其他 Skill 的关联

| 场景 | 用哪个 Skill |
|------|-------------|
| 还没有插件骨架 | 先 **niucloud-addon-scaffold** 生成，再来创建 DIY 组件 |
| 组件需要拉后端数据（文章列表等） | API 接口的 CRUD 部分用 **niucloud-crud-generator**，DIY 组件三件套用本 Skill |
| 插件已有 CRUD，只加 DIY 组件 | 直接用本 Skill |

## 7. 铁律

- DIY 是站点端（app_type=site）能力：装修编辑器从**站点后台**进入。`admin/views/diy/` 目录虽叫 admin，但平台端与站点端共用前端目录，勿因目录名误判为平台端；组件不产生后台菜单，不涉及 `dict/menu/admin.php` 与 `site.php` 的写入。

- path / ComponentKey / 目录三处命名严格一致；ComponentKey 全工程唯一，禁止与系统组件（如 Notice、Text、ImageAds 等）重名。
- 任何链接字段初始值必须写 `['name' => '']`（PHP 侧）/ `{ name: '' }`（JS 侧），禁止空数组或空对象。
- 不改框架 core/ 与系统 app/；组件代码只落在插件目录。
- **数据源后端**：Model 继承 `core\base\BaseModel`，Service 继承 `BaseApiService`，查询必带 `site_id`，URL 不带 `addon/`，命名空间才带。
- 实证参考：`niucloud/addon/cms/app/dict/diy/components.php`（分类 BASIC，组件 Article，path `edit-article`）。

## 8. 常见坑位

| # | 坑 | 解法 |
|---|-----|------|
| 1 | 组件不显示在装修面板 | 检查 dict 格式（return 数组结构）、path 三处一致、ComponentKey 全工程唯一 |
| 2 | 装修态点击组件跳走了 | 渲染组件里缺 `if (diyStore.mode == 'decorate') return` |
| 3 | 组件背景样式不生效 | template 里公共样式字段没保留；渲染组件 wrapCss 没正确读取 |
| 4 | 列表项排序保存后乱了 | 缺 sortablejs；列表项 id 没调 `diyStore.generateRandom()` |
| 5 | 数据接口 404 | URL 多写了 `addon/`；路由没注册到 `app/api/route/route.php` |
| 6 | 数据接口查不到数据 | Service 里缺 `where site_id` 条件 |
| 7 | 新组件前端代码不更新 | 云编译三个端 |
