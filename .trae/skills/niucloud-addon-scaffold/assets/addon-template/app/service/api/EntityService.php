<?php

namespace addon\{{KEY}}\app\service\api;

use addon\{{KEY}}\app\model\Entity;
use core\base\BaseApiService;

/**
 * 示例条目服务层（前台）
 */
class EntityService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Entity();
    }

    /**
     * 获取分页列表
     */
    public function getPage(array $where = [])
    {
        $field = 'id, title, create_time';
        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->withSearch(['title'], $where)
            ->field($field)->order('sort desc, id desc');
        return $this->pageQuery($search_model);
    }

    /**
     * 详情
     */
    public function getInfo(int $id)
    {
        $field = 'id, title, create_time';
        return $this->model
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->field($field)->findOrEmpty()->toArray();
    }
}
