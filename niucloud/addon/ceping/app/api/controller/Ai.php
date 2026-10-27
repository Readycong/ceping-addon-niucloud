<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\AiReportService;
use core\base\BaseApiController;

class Ai extends BaseApiController
{
    /**
     * 请求AI深度报告
     */
    public function request()
    {
        $data = $this->request->params([['record_id', 0]]);
        return success((new AiReportService())->request((int)$data['record_id']));
    }

    /**
     * 轮询报告状态
     */
    public function poll()
    {
        $data = $this->request->params([['record_id', 0]]);
        return success((new AiReportService())->poll((int)$data['record_id']));
    }
}
