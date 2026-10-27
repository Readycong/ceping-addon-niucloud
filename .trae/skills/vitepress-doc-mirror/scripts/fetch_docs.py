#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
静态文档站（VitePress / VuePress / docsify 等）抓取并转 GFM Markdown。

用法:
    python3 fetch_docs.py <doc_base_url> <doc_prefix> <output_dir> [--index /subpath/to/index.html]

示例:
    python3 fetch_docs.py \
        "https://doc.press.niucloud.com" \
        "/php/saas-framework/dev/" \
        ./dev_doc \
        --index /php/saas-framework/dev/

核心流程:
  1. 抓 index_html，提取侧边栏所有 <a href="..."> 指向 .html 且在 doc_prefix 下的链接
  2. 逐页抓 HTML，取 div.vp-doc（或 fallback 到 body）正文
  3. 预处理:
     - 删除 permalink、代码行号、复制按钮
     - Shiki 代码块 -> 标准 <pre class="lang"><code>...</code></pre>（pandoc 2.12 只认 pre 上的语言类）
     - VitePress custom-block -> blockquote（💡 / ⚠️ / ❌ / ℹ️ / 🔍）
     - 内部相对 .html 链接 -> 相对 .md
     - 相对图片路径 -> 绝对 URL
     - 还原 HTML 实体（&nbsp; / &gt; / &lt; / &amp;）
  4. pandoc 转 GFM，围栏归一（``` php -> ```php, ``` text -> ```）
  5. 去掉 vp-doc 外层无属性包裹 div，只取正文
  6. 输出到 output_dir 下，保持源站目录层级

依赖:
  - Python 3.8+   (bs4, urllib, subprocess)
  - pandoc         (brew install pandoc)
  - beautifulsoup4 (pip install beautifulsoup4)
"""

import argparse
import os
import posixpath
import re
import sys
import time
import subprocess
import urllib.request
from bs4 import BeautifulSoup


UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/124.0 Safari/537.36")

CUSTOM_BLOCK_EMOJI = {
    "tip": "💡", "info": "ℹ️", "warning": "⚠️",
    "danger": "❌", "details": "🔍", "caution": "⚠️",
}


# ---------------------------------------------------------------------------
# HTTP
# ---------------------------------------------------------------------------

def fetch(url, retries=3):
    last = None
    for i in range(retries):
        try:
            req = urllib.request.Request(url, headers={"User-Agent": UA})
            with urllib.request.urlopen(req, timeout=30) as r:
                return r.read().decode("utf-8")
        except Exception as e:
            last = e
            time.sleep(1 + i)
    raise last


# ---------------------------------------------------------------------------
# HTML 预处理
# ---------------------------------------------------------------------------

def preprocess(soup, doc_root, doc_prefix, base, current_rel_md):
    """doc_root: 正文容器; current_rel_md: 如 other/xxx.md。"""
    # 1) permalink、复制按钮、代码行号
    for sel in ("a.header-anchor", "button.copy", ".copy.codemeta"):
        for el in doc_root.select(sel):
            el.decompose()
    for sel in ("div.line-numbers", "div.line-numbers-v2", "span.line-number"):
        for el in doc_root.select(sel):
            el.decompose()

    # 2) Shiki 代码块 -> 标准 pre>code，语言类放 <pre>
    for div in doc_root.select("div[class*=language-]"):
        m = re.search(r"language-([\w+-]+)", " ".join(div.get("class", [])))
        lang = m.group(1) if m else ""
        pre = div.find("pre")
        if pre is None:
            continue
        lines = pre.select("span.line")
        code = "\n".join(l.get_text() for l in lines) if lines else pre.get_text()
        newpre = soup.new_tag("pre")
        newpre["class"] = lang if lang else "text"  # 无语言时用 text 兜底，pandoc 也会输出围栏
        newcode = soup.new_tag("code")
        newcode.string = code.strip("\n")
        newpre.append(newcode)
        div.replace_with(newpre)

    # 3) VitePress custom-block -> blockquote
    for div in doc_root.select("div.custom-block"):
        kind = next((c for c in div.get("class", [])
                     if c in CUSTOM_BLOCK_EMOJI), "info")
        emoji = CUSTOM_BLOCK_EMOJI[kind]
        title_p = div.select_one("p.custom-block-title, .custom-block-title")
        title_txt = title_p.get_text(strip=True) if title_p else ""
        if title_p:
            title_p.decompose()
        bq = soup.new_tag("blockquote")
        head = soup.new_tag("p")
        head.string = f"{emoji} {title_txt}".strip()
        bq.append(head)
        for child in list(div.contents):
            bq.append(child.extract())
        div.replace_with(bq)

    # 4) 内部链接 .html -> 相对 .md；图片补全绝对 URL
    cur_dir = posixpath.dirname(current_rel_md)

    def resolve(href):
        if href.startswith(doc_prefix):
            tail = href[len(doc_prefix):]
        else:
            tail = posixpath.normpath(posixpath.join(cur_dir, href))
            if tail.startswith("../"):
                return None
        if tail in ("", "."):
            target = "index.md"
        elif tail.endswith(".html"):
            target = tail[:-5] + ".md"
        else:
            target = tail
        return posixpath.relpath(target, cur_dir) if cur_dir else target

    for a in doc_root.find_all("a", href=True):
        href = a["href"]
        if href.startswith(("http://", "https://", "mailto:", "#")):
            continue
        if ".html" in href:
            target = resolve(href.split("#")[0])
            if target:
                target += ("#" + href.split("#", 1)[1]) if "#" in href else ""
                a["href"] = target

    for img in doc_root.find_all("img", src=True):
        src = img["src"]
        if src.startswith(("http://", "https://", "data:")):
            continue
        img["src"] = (base + src) if src.startswith("/") else posixpath.normpath(
            posixpath.join(base + doc_prefix, cur_dir, src)
        )

    return doc_root


# ---------------------------------------------------------------------------
# pandoc GFM 转换
# ---------------------------------------------------------------------------

def to_markdown(html_fragment):
    p = subprocess.run(
        ["pandoc", "-f", "html", "-t", "gfm-raw_html", "--wrap=none"],
        input=html_fragment, capture_output=True, text=True)
    if p.returncode != 0:
        raise RuntimeError(f"pandoc 失败: {p.stderr}")
    md = p.stdout
    md = (md.replace("\u200b", "")
            .replace("&nbsp;", "")
            .replace("&gt;", ">")
            .replace("&lt;", "<")
            .replace("&amp;", "&"))
    # 围栏归一: ``` php -> ```php, ``` text -> ```
    md = re.sub(
        r"(?m)^```[ \t]+([\w+-]*)[ \t]*$",
        lambda m: "```" + ("" if m.group(1) == "text" else m.group(1)), md)
    # pandoc 转义清理: 只对展示安全的符号去转义，保留反引号、星号等 markdown 敏感字符
    md = md.replace("\\[", "[").replace("\\]", "]")
    md = re.sub(r"\\([#_|~])", r"\1", md)
    # 列表归一: "-   xxx" -> "- xxx"
    md = re.sub(r"(?m)^([-*])[ \t]{2,}", r"\1 ", md)
    md = re.sub(r"(?m)^(\d+\.)[ \t]{2,}", r"\1 ", md)
    md = re.sub(r"\n{3,}", "\n\n", md)
    return md.strip()


# ---------------------------------------------------------------------------
# 主流程
# ---------------------------------------------------------------------------

def collect_paths(index_html, doc_prefix):
    soup = BeautifulSoup(index_html, "html.parser")
    paths = set()
    titles = {}
    for a in soup.find_all("a", href=True):
        h = a["href"]
        if h.startswith(doc_prefix) and not h.endswith(doc_prefix):
            tail = h[len(doc_prefix):]
            if tail.endswith(".html"):
                paths.add(tail)
                txt = a.get_text(" ", strip=True)
                if txt and tail not in titles:
                    titles[tail] = txt
    return sorted(paths), titles


def pick_content(soup):
    """优先 div.vp-doc，其次 div.content，最后 body。"""
    for sel in ("div.vp-doc", "div.content", "div.theme-default-content", "article", "body"):
        c = soup.select_one(sel)
        if c is not None and c.get_text(strip=True):
            return c
    return None


def write_readme(out_dir, index_entries):
    groups = {}
    for rel_md, h1t, side_t in sorted(index_entries):
        g = rel_md.split("/")[0] if "/" in rel_md else "root"
        groups.setdefault(g, []).append((rel_md, h1t or side_t))
    lines = ["# 文档本地镜像", "",
             f"> 来源：抓取生成  ",
             f"> 页面数：{len(index_entries)}  ",
             f"> 抓取日期：{time.strftime('%Y-%m-%d')}", ""]
    for g in sorted(groups):
        lines.append(f"## {g}")
        lines.append("")
        for rel_md, t in groups[g]:
            lines.append(f"- [{t}]({rel_md})")
        lines.append("")
    with open(os.path.join(out_dir, "README.md"), "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def main():
    ap = argparse.ArgumentParser(description="抓取静态文档站转 GFM Markdown")
    ap.add_argument("base", help="站点根，如 https://doc.press.niucloud.com")
    ap.add_argument("prefix", help="文档路径前缀，如 /php/saas-framework/dev/")
    ap.add_argument("out", help="输出目录")
    ap.add_argument("--index", default=None, help="首页 HTML 路径，默认用 prefix")
    args = ap.parse_args()

    base = args.base.rstrip("/")
    doc_prefix = args.prefix
    if not doc_prefix.startswith("/"):
        doc_prefix = "/" + doc_prefix
    out_dir = os.path.abspath(args.out)
    os.makedirs(out_dir, exist_ok=True)

    index_url = base + (args.index or doc_prefix)
    print(f"[1/3] 抓取首页 {index_url}")
    index_html = fetch(index_url)

    print("[2/3] 解析侧边栏链接")
    paths, titles = collect_paths(index_html, doc_prefix)
    print(f"       共 {len(paths)} 个内容页")

    print("[3/3] 逐页下载转换")
    ok, fail, entries = 0, [], []
    for i, tail in enumerate(paths, 1):
        rel_md = tail[:-5] + ".md"
        out_path = os.path.join(out_dir, rel_md)
        os.makedirs(os.path.dirname(out_path) or out_dir, exist_ok=True)
        try:
            html = fetch(base + doc_prefix + tail)
            ps = BeautifulSoup(html, "html.parser")
            content = pick_content(ps)
            if content is None:
                raise RuntimeError("找不到正文容器")
            h1_el = content.find("h1")
            h1_txt = (h1_el.get_text(strip=True).replace("\u200b", "")
                      if h1_el else tail)
            preprocess(ps, content, doc_prefix, base, rel_md)
            # 去掉 vp-doc 内部无属性包裹 div，只取正文
            wrapper = content.find("div", recursive=False)
            if wrapper is not None and not wrapper.get("class"):
                wrapper.unwrap()
            md = to_markdown(content.decode_contents())
            with open(out_path, "w", encoding="utf-8") as f:
                f.write(md + "\n")
            entries.append((rel_md, h1_txt, titles.get(tail, "")))
            ok += 1
            print(f"  [{i}/{len(paths)}] OK  {rel_md}")
        except Exception as e:
            fail.append((tail, str(e)))
            print(f"  [{i}/{len(paths)}] FAIL {tail}: {e}")
        time.sleep(0.25)

    # 首页
    landing_soup = BeautifulSoup(index_html, "html.parser")
    landing = pick_content(landing_soup)
    if landing is not None:
        preprocess(landing_soup, landing, doc_prefix, base, "index.md")
        wrapper = landing.find("div", recursive=False)
        if wrapper is not None and not wrapper.get("class"):
            wrapper.unwrap()
        with open(os.path.join(out_dir, "index.md"), "w", encoding="utf-8") as f:
            f.write(to_markdown(landing.decode_contents()) + "\n")

    write_readme(out_dir, entries)
    print(f"\n完成: 成功 {ok}，失败 {len(fail)}")
    if fail:
        for t, e in fail:
            print("  FAIL:", t, e)
        sys.exit(1)


if __name__ == "__main__":
    main()
