<?php

namespace addon\{{KEY}}\app\service\admin;

use addon\{{KEY}}\app\model\Entity;
use core\base\BaseAdminService;

/**
 * 示例条目服务层（站点后台）
 */
class EntityService extends BaseAdminService
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
        $field = 'id, site_id, title, sort, status, create_time, update_time';
        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['title', 'status'], $where)
            ->field($field)->order('sort desc, id desc');
        return $this->pageQuery($search_model);
    }

    /**
     * 详情
     */
    public function getInfo(int $id)
    {
        $field = 'id, site_id, title, sort, status, create_time, update_time';
        return $this->model
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->field($field)->findOrEmpty()->toArray();
    }

    /**
     * 添加
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        return $this->model->create($data)->id;
    }

    /**
     * 编辑
     */
    public function edit(int $id, array $data)
    {
        $data['update_time'] = time();
        return $this->model
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->update($data);
    }

    /**
     * 删除
     */
    public function del(int $id)
    {
        return $this->model
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->delete();
    }
}
