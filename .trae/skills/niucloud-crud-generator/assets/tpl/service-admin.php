<?php

namespace addon\{{KEY}}\app\service\admin\{{MODULE}};

use addon\{{KEY}}\app\model\{{TABLE_PASCAL}};
use core\base\BaseAdminService;

/**
 * {{COMMENT}}服务层（站点后台）
 */
class {{MODULE_PASCAL}}Service extends BaseAdminService
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
            ->where([['site_id', '=', $this->site_id]])
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

    /**
     * 添加
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['create_time'] = time();
        $res = $this->model->create($data);
        return $res->{{PK}};
    }

    /**
     * 编辑
     */
    public function edit(int $id, array $data)
    {
        $data['update_time'] = time();
        $this->model
            ->where([['{{PK}}', '=', $id], ['site_id', '=', $this->site_id]])
            ->update($data);
        return true;
    }

    /**
     * 删除
     */
    public function del(int $id)
    {
        return $this->model
            ->where([['{{PK}}', '=', $id], ['site_id', '=', $this->site_id]])
            ->delete();
    }
}
