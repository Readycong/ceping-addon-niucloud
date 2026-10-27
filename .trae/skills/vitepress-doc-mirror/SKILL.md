---
name: vitepress-doc-mirror
description: Mirror a static documentation site into local Markdown with cleaned images, links, code fences and custom blocks. Use when the user asks to download, mirror, or batch-convert a VitePress or similar docs site into a local folder, or wants to regenerate a doc summary from a downloaded mirror.
---

# VitePress Doc Mirror

抓一个静态文档站（VitePress 为主，VuePress / docsify 同类结构也适用）到项目本地，转成干净的 GFM Markdown，并生成索引。输出可以直接用于生成「开发经验总结」这类二次文档。

## 什么时候触发

用户说「下载某个文档站到本地」「镜像某个 VitePress 站」「批量转 Markdown」「把 xxx 文档存到 xxx 目录」。不要在用户只要单页内容时用这个技能。

## 依赖检查

执行前确认以下命令可用（失败要先装）：

- `pandoc`（`brew install pandoc`）
- Python 3.8+
- `beautifulsoup4`（`pip install beautifulsoup4`）

## 抓取脚本

仓库内有一个通用脚本：`.trae/skills/vitepress-doc-mirror/scripts/fetch_docs.py`

```bash
python3 .trae/skills/vitepress-doc-mirror/scripts/fetch_docs.py \
  <站点根> <文档路径前缀> <输出目录>
```

示例：
```bash
python3 .trae/skills/vitepress-doc-mirror/scripts/fetch_docs.py \
  "https://doc.press.niucloud.com" \
  "/php/saas-framework/dev/" \
  ./dev_doc
```

脚本做的事：

1. 抓首页，从侧边栏 `<a>` 链接里收集所有 `.html` 页（在前缀路径下）
2. 逐页抓 HTML，取 `div.vp-doc` 或 `div.content` 或 `article` 或 `body`
3. 预处理：
   - 删 permalink / 复制按钮 / 代码行号
   - Shiki 代码块 → 标准 `<pre class="lang"><code>`（pandoc 只认 `<pre>` 上的语言类；无语言时用 `text` 兜底，pandoc 仍输出围栏）
   - VitePress custom-block（tip / warning / danger / info / details / caution）→ blockquote + emoji 前缀
   - 内部相对 `.html` 链接 → 相对 `.md`
   - 相对图片路径 → 绝对 URL（`base + /xxx` 或 `base + prefix + cur_dir + xxx`）
   - 还原 HTML 实体（`&nbsp;` / `&gt;` / `&lt;` / `&amp;`）
4. `pandoc -f html -t gfm-raw_html --wrap=none` 转 GFM
5. 围栏归一：` ``` php ` → ` ```php `，` ``` text ` → ` ``` `
6. 输出到 `<输出目录>/index.md` + 按源站层级铺的各页面 `.md` + `README.md` 分组索引
7. 每页之间 sleep 0.25s，防止触发反爬

## 质量校验（必须做）

转换完立刻跑：

```bash
cd <输出目录>
echo "=== 残留原生HTML ===" && grep -roh '<[a-z][a-z/][^>]*>' --include="*.md" . | grep -vE '^</?(view|template|script|style|slot|el-|text)' | sort | uniq -c | sort -rn | awk 'NR<=20'
echo "=== 残留html链接 ===" && grep -roh '([^)]*\.html[^)]*)' --include="*.md" . | grep -v 'https\?://' | wc -l
echo "=== 非绝对图片 ===" && grep -roh '!\[[^]]*\]([^)]*)' --include="*.md" . | grep -vE '^(https://|data:)' | wc -l
echo "=== 残留HTML实体 ===" && grep -roh '&[a-z]*;' --include="*.md" . | sort | uniq -c
```

判定标准：

- 「残留原生HTML」里如果只有 Vue 示例代码里的 `<view>` / `<template>` / `<style>` / `<script>` / Element Plus 组件，那是正常的业务代码示例，不是 bug
- 「残留 html 链接」= 0；如果有，必须是外部站（如 `https://doc.niucloud.com/...`），内部相对链接应全部转成 `.md`
- 「非绝对图片」= 0；允许 `data:` 内嵌 base64
- 「残留 HTML 实体」= 0

如果校验不过，看 `fetch_docs.py` 的 preprocess / to_markdown 函数，按失败项追加修复逻辑。

## 输出结构

```
<输出目录>/
├── index.md           # 首页
├── README.md          # 自动生成的分组索引
├── Api/               # 按源站子目录铺
│   └── api.md
├── other/...
└── pluginDev/...
```

README.md 自动按顶层目录分组。如果你想自己组织分组，直接改 README.md。

## 常见坑

| # | 坑 | 解法 |
|---|-----|------|
| 1 | pandoc 2.12 不认 `<code class="language-php">` 的语言类 | 必须把语言类放 `<pre>` 上，脚本已做 |
| 2 | 无语言的代码块被 pandoc 转成缩进式而不是围栏 | 给 `<pre>` 加 `class="text"`，pandoc 仍输出 ` ``` ` 围栏 |
| 3 | VitePress custom-block 不生效 | 确认源站是 `div.custom-block.tip` 结构，不是别的 CSS 框架 |
| 4 | docsify / VuePress 侧边栏链接格式不同 | 调整 `collect_paths` 的 CSS 选择器，脚本里 `href.startswith(doc_prefix) and endswith(.html)` 的过滤最稳 |
| 5 | 站点有反爬，sleep 不够 | 加 `time.sleep(0.5)` 或换 UA |
| 6 | 图片路径是 `/assets/xxx.png` 但 base 不是文档根 | 用参数 `base` + 相对路径拼接；脚本已覆盖 |
| 7 | 首页不是 `prefix` 而是别的 HTML | 用 `--index` 参数指定 |

## 二次加工：开发经验总结

镜像完通常要再读一遍核心页面，结合项目自己的源码，生成一份「xxx 系统开发经验.md」。经验总结的结构：

1. 框架定位与架构心智（SaaS 多租户、应用 vs 插件、二开黄金原则）
2. 插件目录结构（附 `info.json` / `install.sql` 模板）
3. 后端四层架构（Controller → Service → Model → DB + Validate）
4. 三层 Service 基类及其自动注入的请求上下文
5. 数据库与命名规范
6. 菜单与权限（三层 + 替换框架菜单的软删除技巧）
7. 事件钩子（解耦神器）
8. 异步任务双轨：消息队列（Redis）+ 计划任务（内置 Cron）
9. 前端开发要点（Admin / uni-app / Web / DIY 装修）
10. 开发工作流
11. 常见坑位表（ThinkPHP 升级兼容等）
12. 参考文档（回链到 dev_doc 下对应的 md 文件）

**经验总结必须回链 dev_doc**，让读者可以直接跳到原文。

---

## 工作清单（每次执行时照着走）

1. ✅ 确认 pandoc + bs4 可用
2. ✅ 跑 `fetch_docs.py base prefix out`
3. ✅ 跑质量校验脚本，不过则修 preprocess
4. ✅ 抽查 3-5 个页面确认代码围栏 / 自定义容器 / 链接 / 图片都对
5. ✅ 需要的话读核心 md 页 + 项目源码生成经验总结
6. ✅ 回链 dev_doc 路径
