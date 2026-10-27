<?php

namespace addon\ceping\app\service\admin;

use addon\ceping\app\service\core\CepingConfigService;
use core\base\BaseAdminService;

/**
 * 后台AI/广告配置
 */
class ConfigService extends BaseAdminService
{
    public function getConfig(): array
    {
        return (new CepingConfigService())->getConfig($this->site_id);
    }

    public function setConfig(array $data): array
    {
        $allow = [
            'ai_enabled', 'ai_base_url', 'ai_api_key', 'ai_model', 'ai_timeout',
            'prompt_ver', 'ad_enabled', 'ad_unit_id', 'invite_expire',
            'draft_expire_h', 'hot_test_ids',
        ];
        $payload = [];
        foreach ($allow as $k) {
            if (array_key_exists($k, $data)) $payload[$k] = $data[$k];
        }
        // 类型规整
        foreach (['ai_enabled', 'ad_enabled'] as $k) {
            if (array_key_exists($k, $payload)) $payload[$k] = (int)!!$payload[$k];
        }
        foreach (['ai_timeout', 'invite_expire', 'draft_expire_h'] as $k) {
            if (array_key_exists($k, $payload)) $payload[$k] = (int)$payload[$k];
        }
        if (isset($payload['hot_test_ids']) && is_string($payload['hot_test_ids'])) {
            $payload['hot_test_ids'] = array_values(array_filter(array_map('intval', explode(',', $payload['hot_test_ids']))));
        }
        return (new CepingConfigService())->setConfig($this->site_id, $payload);
    }
}
