<?php

namespace addon\ceping\app\model;

use core\base\BaseModel;

/**
 * 会员站内资料模型
 */
class MemberProfile extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'ceping_member_profile';

    protected $type = [
        'site_id'     => 'integer',
        'member_id'   => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];
}
