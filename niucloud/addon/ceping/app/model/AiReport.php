<?php

namespace addon\ceping\app\model;

use core\base\BaseModel;
use think\model\concern\SoftDelete;

/**
 * AI报告模型
 */
class AiReport extends BaseModel
{
    use SoftDelete;

    protected $pk = 'id';
    protected $name = 'ceping_ai_report';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $type = [
        'site_id'      => 'integer',
        'member_id'    => 'integer',
        'record_id'    => 'integer',
        'test_id'      => 'integer',
        'content'      => 'array',
        'is_cached'    => 'integer',
        'is_safety'    => 'integer',
        'create_time'  => 'timestamp',
        'update_time'  => 'timestamp',
    ];
}
