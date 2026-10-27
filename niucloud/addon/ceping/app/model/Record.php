<?php

namespace addon\ceping\app\model;

use core\base\BaseModel;
use think\model\concern\SoftDelete;

/**
 * 测评记录模型
 */
class Record extends BaseModel
{
    use SoftDelete;

    protected $pk = 'id';
    protected $name = 'ceping_record';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $type = [
        'site_id'      => 'integer',
        'member_id'    => 'integer',
        'test_id'      => 'integer',
        'answers'      => 'array',
        'score_detail' => 'array',
        'total_score'  => 'integer',
        'invite_id'    => 'integer',
        'partner_record_id' => 'integer',
        'create_time'  => 'integer',
    ];

    public function searchTestIdAttr($query, $value)
    {
        if ($value > 0) $query->where('test_id', (int)$value);
    }

    public function searchMemberIdAttr($query, $value)
    {
        if ($value > 0) $query->where('member_id', (int)$value);
    }

    public function searchModeAttr($query, $value)
    {
        if ($value !== '') $query->where('mode', $value);
    }

    public function searchCreateTimeAttr($query, $value)
    {
        if ($value && is_array($value) && count($value) == 2) {
            $query->whereBetweenTime('create_time', $value[0], $value[1]);
        }
    }
}
