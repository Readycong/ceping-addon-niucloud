<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\PartnerService;
use core\base\BaseApiController;

class Partner extends BaseApiController
{
    /**
     * A发起双人邀请
     */
    public function invite()
    {
        $data = $this->request->params([
            ['answers', []],
            ['source', 'home'],
        ]);
        return success((new PartnerService())->invite($data['answers'], $data['source']));
    }

    /**
     * B查看邀请信息
     */
    public function join()
    {
        $data = $this->request->params([['token', '']]);
        return success((new PartnerService())->joinInfo($data['token']));
    }

    /**
     * B提交作答并配对
     */
    public function submit()
    {
        $data = $this->request->params([
            ['token', ''],
            ['answers', []],
        ]);
        return success((new PartnerService())->submit($data['token'], $data['answers']));
    }
}
