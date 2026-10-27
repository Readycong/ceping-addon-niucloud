<?php

namespace addon\{{KEY}}\app\validate;

use think\Validate;

/**
 * 示例条目验证器
 */
class Entity extends Validate
{
    protected $rule = [
        'title' => 'require|max:50',
        'sort' => 'number|between:0,10000',
        'status' => 'number|between:0,1',
    ];

    protected $message = [
        'title.require' => '{{KEY}}_validate.title_require',
        'title.max' => '{{KEY}}_validate.title_max',
    ];

    protected $scene = [
        'add' => ['title', 'sort', 'status'],
        'edit' => ['title', 'sort', 'status'],
    ];
}
