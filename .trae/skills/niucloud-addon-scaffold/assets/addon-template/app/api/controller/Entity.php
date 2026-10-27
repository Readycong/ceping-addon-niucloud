<?php

namespace addon\{{KEY}}\app\api\controller;

use addon\{{KEY}}\app\service\api\EntityService;
use core\base\BaseApiController;

/**
 * 示例条目控制器（前台）
 */
class Entity extends BaseApiController
{
    /**
     * 列表
     */
    public function lists()
    {
        $data = $this->request->params([
            ['title', ''],
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
}
