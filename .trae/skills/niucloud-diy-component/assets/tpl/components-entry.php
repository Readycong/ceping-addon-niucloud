<?php

// 将以下条目合并进 app/dict/diy/components.php：
// - 文件不存在则新建：return [ '{{CATEGORY}}' => [ 'title' => '分类名', 'list' => [ /* 此处放条目 */ ] ] ];
// - 文件已存在则把条目放入对应分类（默认 BASIC）的 list 中；分类不存在则新建。

return [
    '{{COMPONENT_PASCAL}}' => [
        'title' => '{{TITLE}}',
        'icon' => 'iconfont iconapp',                // 组件库面板图标（iconfont class）
        'path' => '{{PATH}}',                        // 必须与 admin 编辑组件文件名、uni-app 渲染组件目录一致（kebab-case）
        'support_page' => [],                        // 限定页面类型 key，空数组=所有页面可用
        'uses' => 0,                                 // 固定 0
        'sort' => 10000,                             // 面板中排序
        'position' => '',                            // 一般留空
        'template' => [
            // ===== 公共样式默认值（框架统一渲染，编辑组件通过 <slot name="style"/> 暴露）=====
            'textColor' => '#333333',
            'pageStartBgColor' => '', 'pageEndBgColor' => '', 'pageGradientAngle' => '0deg',
            'componentBgUrl' => '',
            'componentBgAlpha' => 5,                 // 背景遮罩 0-10
            'componentStartBgColor' => '', 'componentEndBgColor' => '', 'componentGradientAngle' => '0deg',
            'topRounded' => 0, 'bottomRounded' => 0,
            'elementBgColor' => '',
            'topElementRounded' => 0, 'bottomElementRounded' => 0,
            'marginTop' => 0, 'marginBottom' => 0, 'marginBoth' => 0,
        ],
        'value' => [
            // ===== 业务数据初始值（按组件实际字段填写）=====
            // 示例：
            // 'source' => 'auto',
            // 'count' => 4,
            // 'list' => [],
            // 含链接的字段必须：'link' => ['name' => ''],   // 禁止写空数组
        ],
    ],
];
