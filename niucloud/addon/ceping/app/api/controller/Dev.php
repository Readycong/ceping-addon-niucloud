<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\DevAuthService;
use core\base\BaseApiController;

/**
 * 开发联调专用（app_debug=true 才可用）
 */
class Dev extends BaseApiController
{
    /**
     * 模拟微信openid静默登录
     */
    public function mockLogin()
    {
        $data = $this->request->params([['openid', '']]);
        return success((new DevAuthService())->mockLogin($data['openid']));
    }
}
