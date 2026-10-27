# 题库文件结构规范（Schema v2）

> 本目录每个选题一个独立 **JSON** 文件，开发可直接 import。所有测试均为**单选题（每题 4 个选项 A/B/C/D）**，娱乐属性、非诊断。
> 文件名格式：`{id:03d}_{slug}.json`，如 `001_mbti.json`。
> **商业版权类**文件名加后缀 `_commercial`，且 `title` 前缀加 `【商业版权·原题参考】`，`copyrightStatus` 记为 `commercial`。

## 顶层字段

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | int | 选题编号（1–50） |
| `title` | string | 完整标题；商业版权类须以【商业版权·原题参考】开头 |
| `shortName` | string | 短名（列表/分享用） |
| `category` | string | 大类（人格/情感/职场/生活/社交/玄学/健康趣味/自我成长…） |
| `tags` | string[] | 标签 |
| `copyrightStatus` | string | `commercial` / `public_domain` / `theory` / `internet_native` |
| `entertainment` | bool | 固定 true |
| `compliance` | object | 合规声明 |
| `source` | object | 来源、权利人、授权渠道、逐字题面说明 |
| `description` | string | 测试说明 |
| `instructions` | string | 作答须知 |
| `estimatedMinutes` | int | 预计分钟 |
| `questionCount` | int | 题目数 |
| `scoringModel` | string | `dimension` / `category` / `threshold` |
| `axes` / `categories` / `bands` | array | 按模型选用 |
| `tieBreak` | string | 平票/边界处理 |
| `originalItemBank` | object | **商业版权类专有**：还原样题与逐字题面说明 |
| `questions` | array | 正式题目（自编版，落地用） |
| `results` | array | 结果/分类及解说 |
| `reDeriveHint` | string | 商业版权类专有：后续自编重推的方向提示 |

## compliance

```json
"compliance": {
  "nonDiagnostic": true,
  "note": "本测试为娱乐性分类，结果不代表任何心理评估或医学诊断。",
  "minorProtected": false
}
```
> 涉及情绪/轻心理健康来源（#8/#16/#19/#31/#32/#33/#47/#49 等）须明确"娱乐性质，不代表专业心理评估"，正文禁"建议就医"等医疗措辞。

## source（含商业版权字段）

```json
"source": {
  "theory": "理论/量表出处全称",
  "officialOwner": "权利人（商业版权类必填）",
  "sourceType": "commercial | public_domain | theory | internet_native",
  "licensing": "获取正版逐字题面的授权渠道",
  "verbatimNote": "逐字题面说明：官方题面为专有资产，本文件所载为基于公开资料还原的代表性样题，非官方逐字原文；如需逐字商用须获授权。"
}
```

## 计分模型（整份文件只用一种）

### A. dimension（人格轴模型：MBTI / 依恋 / 九型 / TKI）
```json
"scoringModel": "dimension",
"axes": [
  {"key":"EI","name":"能量指向","poles":[{"key":"E","label":"外向E"},{"key":"I","label":"内向I"}]}
],
"tieBreak": "某轴平票时默认取第二个 pole（如 EI 平票取 I）。"
```
选项 score：`{"axis":"EI","pole":"E"}`
结果 match：`{"EI":"I","SN":"N","TF":"F","JP":"P"}`（可用 "*" 通配）

### B. category（你是哪种 X，分类累加）
```json
"scoringModel": "category",
"categories": [{"key":"foodie","name":"吃货型"}],
"tieBreak": "平票时按结果数组顺序优先。"
```
选项 score：`{"cat":"foodie","points":1}`
结果用 `categoryKey` 命中。

### C. threshold（指数/等级，分值区间）
```json
"scoringModel": "threshold",
"bands": [{"key":"low","name":"低","min":0,"max":20}],
"tieBreak": "分数落在边界取高一级。"
```
选项 score：`{"points":3}`

## questions（每题 4 选项）

```json
"questions": [
  {
    "no": 1,
    "stem": "周末的理想打开方式是？",
    "options": [
      {"key":"A","text":"约朋友出去浪","score":{"axis":"EI","pole":"E"}},
      {"key":"B","text":"宅家独处","score":{"axis":"EI","pole":"I"}},
      {"key":"C","text":"看心情","score":{"axis":"EI","pole":"E"}},
      {"key":"D","text":"先安排结构化的事","score":{"axis":"EI","pole":"I"}}
    ]
  }
]
```

## results（分类及其解说）

```json
"results": [
  {
    "key": "INFP",
    "name": "调停者",
    "match": {"EI":"I","SN":"N","TF":"F","JP":"P"},
    "summary": "温柔的理想主义者。",
    "detail": "详细解说（100–300 字）。",
    "traits": ["共情力强","有创造力"],
    "growthTip": "小心内耗，把理想落地一小步。",
    "shareCard": "我是 INFP 调停者，你呢？",
    "adSlot": "result_page | share_card | info_feed"
  }
]
```

## 商业版权类专有结构 originalItemBank

```json
"originalItemBank": {
  "structure": "官方量表结构说明（题量/题型/计分方式）",
  "itemForm": "题面形式（如对偶偏好题、成对陈述二选一、分数分配题）",
  "note": "还原样题说明：非官方逐字原文，仅用于理解出题逻辑",
  "items": [
    {
      "no": 1,
      "stem": "题面",
      "options": [
        {"key":"A","text":"选项A","maps":"EI/E"},
        {"key":"B","text":"选项B","maps":"EI/I"}
      ]
    }
  ]
}
```
