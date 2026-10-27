<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\AdService;
use core\base\BaseApiController;

class Ad extends BaseApiController
{
    /**
     * 激励视频解锁校验（幂等）
     */
    public function verify()
    {
        $data = $this->request->params([
            ['record_id', 0],
            ['ad_type', 'reward'],
        ]);
        return success((new AdService())->verify((int)$data['record_id'], $data['ad_type']));
    }
}
