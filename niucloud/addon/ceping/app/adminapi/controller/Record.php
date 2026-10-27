<?php

namespace addon\ceping\app\adminapi\controller;

use addon\ceping\app\service\admin\RecordService;
use core\base\BaseAdminController;

class Record extends BaseAdminController
{
    /**
     * 测评记录列表
     */
    public function lists()
    {
        $data = $this->request->params([
            ['member_id', 0],
            ['test_id', 0],
            ['mode', ''],
            ['create_time', []],
        ]);
        return success((new RecordService())->getPage($data));
    }

    /**
     * 记录详情
     */
    public function info()
    {
        $data = $this->request->params([['id', 0]]);
        return success((new RecordService())->getInfo((int)$data['id']));
    }
}
