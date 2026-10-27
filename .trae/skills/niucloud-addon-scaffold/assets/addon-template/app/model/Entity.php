<?php

namespace addon\{{KEY}}\app\model;

use core\base\BaseModel;

/**
 * 示例条目模型
 */
class Entity extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称（不含前缀的表名）
     * @var string
     */
    protected $name = '{{KEY}}';

    protected $type = [
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];

    /**
     * 标题搜索器
     */
    public function searchTitleAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('title', 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }

    /**
     * 状态搜索器
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }
}
