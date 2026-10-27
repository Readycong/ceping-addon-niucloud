<?php

namespace addon\ceping\app\model;

use core\base\BaseModel;

/**
 * 测评题库模型
 */
class Test extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'ceping_test';

    protected $type = [
        'site_id'        => 'integer',
        'test_id'        => 'integer',
        'tags'           => 'array',
        'axes'           => 'array',
        'categories'     => 'array',
        'bands'          => 'array',
        'questions'      => 'array',
        'results'        => 'array',
        'compliance'     => 'array',
        'question_count' => 'integer',
        'result_count'   => 'integer',
        'est_minutes'    => 'integer',
        'online_status'  => 'integer',
        'is_hot'         => 'integer',
        'sort'           => 'integer',
        'create_time'    => 'timestamp',
        'update_time'    => 'timestamp',
    ];

    public function searchCategoryAttr($query, $value)
    {
        if ($value !== '') $query->where('category', $value);
    }

    public function searchScoringModelAttr($query, $value)
    {
        if ($value !== '') $query->where('scoring_model', $value);
    }

    public function searchCopyrightStatusAttr($query, $value)
    {
        if ($value !== '') $query->where('copyright_status', $value);
    }

    public function searchOnlineStatusAttr($query, $value)
    {
        if ($value !== '') $query->where('online_status', (int)$value);
    }

    public function searchKeywordAttr($query, $value)
    {
        if ($value !== '') {
            $kw = '%' . $this->handelSpecialCharacter($value) . '%';
            $query->where(function ($q) use ($kw) {
                $q->whereLike('title', $kw)->whereOr('short_name', 'like', $kw)->whereOr('display_name', 'like', $kw);
            });
        }
    }
}
