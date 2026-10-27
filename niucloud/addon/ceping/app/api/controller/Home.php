<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\TestService;
use core\base\BaseApiController;

class Home extends BaseApiController
{
    /**
     * 首页聚合
     */
    public function index()
    {
        return success((new TestService())->home());
    }

    /**
     * 金刚区分类
     */
    public function categories()
    {
        return success((new TestService())->categories());
    }
}
