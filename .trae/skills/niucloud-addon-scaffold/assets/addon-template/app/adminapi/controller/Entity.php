<?php

namespace addon\{{KEY}}\app\adminapi\controller;

use addon\{{KEY}}\app\service\admin\EntityService;
use core\base\BaseAdminController;

/**
 * 示例条目控制器
 */
class Entity extends BaseAdminController
{
    /**
     * 列表
     */
    public function lists()
    {
        $data = $this->request->params([
            ['title', ''],
            ['status', ''],
        ]);
        return success((new EntityService())->getPage($data));
    }

    /**
     * 详情
     */
    public function info(int $id)
    {
        return success((new EntityService())->getInfo($id));
    }

    /**
     * 添加
     */
    public function add()
    {
        $data = $this->request->params([
            ['title', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, 'addon\{{KEY}}\app\validate\Entity.add');
        $id = (new EntityService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 编辑
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ['title', ''],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->validate($data, 'addon\{{KEY}}\app\validate\Entity.edit');
        (new EntityService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除
     */
    public function del(int $id)
    {
        (new EntityService())->del($id);
        return success('DELETE_SUCCESS');
    }
}
