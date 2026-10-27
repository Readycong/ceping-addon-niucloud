<?php

namespace addon\ceping\app\model;

use core\base\BaseModel;

/**
 * 双人邀请模型
 */
class PartnerInvite extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'ceping_partner_invite';

    protected $type = [
        'site_id'             => 'integer',
        'test_id'             => 'integer',
        'inviter_member_id'   => 'integer',
        'inviter_record_id'   => 'integer',
        'invitee_member_id'   => 'integer',
        'invitee_record_id'   => 'integer',
        'expire_time'         => 'integer',
        'paired_time'         => 'integer',
        'create_time'         => 'integer',
    ];
}
