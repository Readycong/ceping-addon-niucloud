<?php

namespace addon\ceping\app\service\core;

use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;

/**
 * 爱测评配置（sys_config key=ceping_config）
 */
class CepingConfigService extends BaseCoreService
{
    const CONFIG_KEY = 'ceping_config';

    public function defaultConfig(): array
    {
        return [
            // AI
            'ai_enabled'       => 0,
            'ai_base_url'      => '',
            'ai_api_key'       => '',
            'ai_model'         => '',
            'ai_timeout'       => 30,
            'prompt_ver'       => 'v1',
            // 激励视频
            'ad_enabled'       => 0,
            'ad_unit_id'       => '',
            // 双人
            'invite_expire'    => 24 * 3600,
            // 其他
            'draft_expire_h'   => 24,
            'hot_test_ids'     => [5, 9, 11, 7, 2, 24, 1, 50],
            'mock_openid'      => '',
        ];
    }

    public function getConfig(int $site_id): array
    {
        $cfg = (new CoreConfigService())->getConfigValue($site_id, self::CONFIG_KEY);
        return array_merge($this->defaultConfig(), is_array($cfg) ? $cfg : []);
    }

    public function setConfig(int $site_id, array $data): array
    {
        $current = $this->getConfig($site_id);
        $merged = array_merge($current, $data);
        (new CoreConfigService())->setConfig($site_id, self::CONFIG_KEY, $merged);
        return $merged;
    }

    /**
     * 供前台使用的安全配置（隐藏密钥）
     */
    public function getPublicConfig(int $site_id): array
    {
        $cfg = $this->getConfig($site_id);
        return [
            'ai_enabled'    => (int)$cfg['ai_enabled'] === 1 && !empty($cfg['ai_base_url']) && !empty($cfg['ai_model']),
            'ad_enabled'    => (int)$cfg['ad_enabled'] === 1 && !empty($cfg['ad_unit_id']),
            'ad_unit_id'    => $cfg['ad_unit_id'],
            'draft_expire_h' => (int)$cfg['draft_expire_h'],
        ];
    }
}
