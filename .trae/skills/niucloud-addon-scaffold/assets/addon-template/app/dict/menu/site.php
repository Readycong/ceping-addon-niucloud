<?php

// 站点后台菜单（app_type=site，站点账号登录，页面 URL 前缀 /site/ 由框架自动补）。
// 普通业务插件的管理菜单只写本文件；平台端菜单 admin.php 必须保持空数组，严禁对称复制。

return [
    [
        'menu_name' => '{{TITLE}}',
        'menu_key' => '{{KEY}}',
        'menu_short_name' => '{{SHORT_TITLE}}',
        'parent_key' => '',
        'parent_select_key' => '',
        'menu_type' => '0',
        'icon' => 'iconfont iconapp',
        'api_url' => '',
        'router_path' => '',
        'view_path' => '',
        'methods' => '',
        'sort' => '100',
        'status' => '1',
        'is_show' => '1',
        'children' => [
            [
                'menu_name' => '示例列表',
                'menu_key' => '{{KEY}}_item_list',
                'menu_short_name' => '示例',
                'parent_select_key' => '',
                'menu_type' => '1',
                'icon' => 'element List',
                'api_url' => '{{KEY}}/item',
                'router_path' => '{{KEY}}/item/list',
                'view_path' => 'item/list',
                'methods' => 'get',
                'sort' => '100',
                'status' => '1',
                'is_show' => '1',
            ],
        ],
    ],
];
