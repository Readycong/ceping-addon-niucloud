<?php

namespace addon\ceping\app\model;

use core\base\BaseModel;

/**
 * 广告解锁记录模型
 */
class UnlockLog extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'ceping_unlock_log';

    protected $type = [
        'site_id'       => 'integer',
        'member_id'     => 'integer',
        'record_id'     => 'integer',
        'test_id'       => 'integer',
        'verify_status' => 'integer',
        'create_time'   => 'timestamp',
    ];
}
