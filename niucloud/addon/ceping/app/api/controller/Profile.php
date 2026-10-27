<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\ProfileService;
use core\base\BaseApiController;

class Profile extends BaseApiController
{
    public function info()
    {
        return success((new ProfileService())->get());
    }

    public function update()
    {
        $data = $this->request->params([
            ['nickname', null],
            ['avatar_key', null],
        ]);
        return success((new ProfileService())->update($data['nickname'], $data['avatar_key']));
    }
}
