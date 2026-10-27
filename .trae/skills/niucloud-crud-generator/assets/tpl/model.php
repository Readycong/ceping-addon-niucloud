<?php

namespace addon\{{KEY}}\app\model;

use core\base\BaseModel;

/**
 * {{COMMENT}}模型
 */
class {{TABLE_PASCAL}} extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = '{{PK}}';

    /**
     * 模型名称（不含全局前缀的完整表名）
     * @var string
     */
    protected $name = '{{TABLE}}';

    protected $type = [
        // 按需启用（列存在时）：
        // 'create_time' => 'timestamp',
        // 'update_time' => 'timestamp',
        // 'pay_time' => 'timestamp',
    ];

    // JSON 字段（按需启用）：
    // protected $json = ['extend_info'];
    // protected $jsonAssoc = true;

    // 软删除（仅当表含 delete_time 字段时启用）：
    // use think\model\concern\SoftDelete;
    // protected $deleteTime = 'delete_time';
    // protected $defaultSoftDelete = 0;

    // ===== 搜索器：仅对“需要筛选”的字段生成，由 withSearch 触发 =====

    /**
     * 主名称模糊搜索（示例：标题/名称类字段，无则删除）
     */
    public function searchNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('{{NAME_FIELD}}', 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }

    /**
     * 状态精确搜索（示例：status 类字段，无则删除）
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('{{STATUS_FIELD}}', $value);
        }
    }

    /**
     * 创建时间范围搜索（表含 create_time 时启用）
     */
    public function searchCreateTimeAttr($query, $value, $data)
    {
        $start_time = empty($value[0]) ? 0 : strtotime($value[0]);
        $end_time = empty($value[1]) ? 0 : strtotime($value[1]);
        if ($start_time > 0 && $end_time > 0) {
            $query->whereBetweenTime('create_time', $start_time, $end_time);
        } else if ($start_time > 0) {
            $query->where([['create_time', '>=', $start_time]]);
        } else if ($end_time > 0) {
            $query->where([['create_time', '<=', $end_time]]);
        }
    }
}
