<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
            // *********************************** ceping 爱测评 ***********************************
            {
                "root": "addon/ceping",
                "pages": [
                    {
                        "path": "pages/home/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "爱测评"
                        }
                    },
                    {
                        "path": "pages/cats/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "全部分类"
                        }
                    },
                    {
                        "path": "pages/category/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "分类"
                        }
                    },
                    {
                        "path": "pages/search/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "搜索测评"
                        }
                    },
                    {
                        "path": "pages/intro/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "测评介绍"
                        }
                    },
                    {
                        "path": "pages/quiz/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "答题中"
                        }
                    },
                    {
                        "path": "pages/result/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "测评结果"
                        }
                    },
                    {
                        "path": "pages/poster/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "测评海报"
                        }
                    },
                    {
                        "path": "pages/history/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "我的记录"
                        }
                    },
                    {
                        "path": "pages/invite/index",
                        "style": {
                            "navigationStyle": "custom",
                            "navigationBarTitleText": "双人邀请"
                        }
                    }
                ]
            },
            // PAGE_END
EOT
];