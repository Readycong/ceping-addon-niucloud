<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/{{KEY}}",
            "pages": [
                {
                    "path": "pages/item/list",
                    "style": {
                        "navigationBarTitleText": "{{TITLE}}"
                    }
                }
            ]
        },
        // PAGE_END
EOT
];
