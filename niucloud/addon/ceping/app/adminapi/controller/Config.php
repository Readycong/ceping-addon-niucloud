<?php

namespace addon\ceping\app\adminapi\controller;

use addon\ceping\app\service\admin\ConfigService;
use core\base\BaseAdminController;

class Config extends BaseAdminController
{
    /**
     * AI/广告配置
     */
    public function index()
    {
        return success((new ConfigService())->getConfig());
    }

    public function set()
    {
        $data = $this->request->params([
            ['ai_enabled', 0],
            ['ai_base_url', ''],
            ['ai_api_key', ''],
            ['ai_model', ''],
            ['ai_timeout', 30],
            ['prompt_ver', 'v1'],
            ['ad_enabled', 0],
            ['ad_unit_id', ''],
            ['invite_expire', 86400],
            ['draft_expire_h', 24],
            ['hot_test_ids', []],
        ]);
        return success((new ConfigService())->setConfig($data));
    }
}
