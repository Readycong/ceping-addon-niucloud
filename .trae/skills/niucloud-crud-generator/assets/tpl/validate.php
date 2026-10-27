<?php

namespace addon\{{KEY}}\app\validate\{{MODULE}};

use think\Validate;

/**
 * {{COMMENT}}验证器
 */
class {{MODULE_PASCAL}} extends Validate
{
    protected $rule = [
        // 依据表结构生成。NOT NULL 且无默认值的业务字段加 require；
        // varchar(n) 加 max:n；int 加 number；decimal 加 float；
        // 示例（无则删）：
        '{{NAME_FIELD}}' => 'require|max:100',
        '{{STATUS_FIELD}}' => 'number|between:0,1',
        'sort' => 'number|between:0,10000',
    ];

    protected $message = [
        '{{NAME_FIELD}}.require' => '{{MODULE}}_validate.name_require',
        '{{NAME_FIELD}}.max' => '{{MODULE}}_validate.name_max',
    ];

    protected $scene = [
        'add' => [ /* 与 add 表单字段一致 */ ],
        'edit' => [ /* 与 edit 表单字段一致 */ ],
    ];
}
