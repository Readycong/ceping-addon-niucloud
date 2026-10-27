<?php

namespace addon\{{KEY}}\app\api\controller\{{MODULE}};

use addon\{{KEY}}\app\service\api\{{MODULE}}\{{MODULE_PASCAL}}Service;
use core\base\BaseApiController;

/**
 * {{COMMENT}}控制器（前台）
 */
class {{MODULE_PASCAL}} extends BaseApiController
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
}
