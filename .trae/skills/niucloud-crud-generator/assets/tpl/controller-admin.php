<?php

namespace addon\{{KEY}}\app\adminapi\controller\{{MODULE}};

use addon\{{KEY}}\app\service\admin\{{MODULE}}\{{MODULE_PASCAL}}Service;
use core\base\BaseAdminController;

/**
 * {{COMMENT}}控制器
 */
class {{MODULE_PASCAL}} extends BaseAdminController
{
    /**
     * 列表
     */
    public function lists()
    {
        $data = $this->request->params([
            {{SEARCH_PARAMS}}
        ]);
        return success((new {{MODULE_PASCAL}}Service())->getPage($data));
    }

    /**
     * 详情
     */
    public function info(int $id)
    {
        return success((new {{MODULE_PASCAL}}Service())->getInfo($id));
    }

    /**
     * 添加
     */
    public function add()
    {
        $data = $this->request->params([
            {{FORM_PARAMS}}
        ]);
        $this->validate($data, 'addon\{{KEY}}\app\validate\{{MODULE}}\{{MODULE_PASCAL}}.add');
        $id = (new {{MODULE_PASCAL}}Service())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 编辑
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            {{FORM_PARAMS}}
        ]);
        $this->validate($data, 'addon\{{KEY}}\app\validate\{{MODULE}}\{{MODULE_PASCAL}}.edit');
        (new {{MODULE_PASCAL}}Service())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 删除
     */
    public function del(int $id)
    {
        (new {{MODULE_PASCAL}}Service())->del($id);
        return success('DELETE_SUCCESS');
    }
}
