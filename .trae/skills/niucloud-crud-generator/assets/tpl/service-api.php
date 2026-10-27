<?php

namespace addon\{{KEY}}\app\service\api\{{MODULE}};

use addon\{{KEY}}\app\model\{{TABLE_PASCAL}};
use core\base\BaseApiService;

/**
 * {{COMMENT}}服务层（前台）
 */
class {{MODULE_PASCAL}}Service extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new {{TABLE_PASCAL}}();
    }

    /**
     * 获取分页列表
     */
    public function getPage(array $where = [])
    {
        $field = '{{LIST_FIELDS}}';
        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id]/* , ['status', '=', 1] 状态字段存在时启用 */])
            ->withSearch([{{SEARCH_FIELDS}}], $where)
            ->field($field)->order('{{ORDER}}');
        return $this->pageQuery($search_model);
    }

    /**
     * 详情
     */
    public function getInfo(int $id)
    {
        $field = '{{ALL_FIELDS}}';
        return $this->model
            ->where([['{{PK}}', '=', $id], ['site_id', '=', $this->site_id]])
            ->field($field)->findOrEmpty()->toArray();
    }
}
