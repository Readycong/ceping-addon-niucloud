<?php

namespace addon\ceping\app\service\api;

use addon\ceping\app\model\Record;
use addon\ceping\app\model\UnlockLog;
use addon\ceping\app\service\core\CepingConfigService;
use core\base\BaseApiService;
use core\exception\ApiException;

/**
 * 激励视频解锁（服务端幂等记录）
 */
class AdService extends BaseApiService
{
    public function verify(int $record_id, string $ad_type = 'reward'): array
    {
        $record = (new Record())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)->where('id', $record_id)->findOrEmpty();
        if ($record->isEmpty()) throw new ApiException('CEPING_RECORD_NOT_FOUND');

        $cfg = (new CepingConfigService())->getConfig($this->site_id);

        // 开发兜底：DEBUG且未配置广告位时允许直接解锁，便于全链路联调
        $dev_bypass = !$cfg['ad_enabled'] && env('app_debug', false);
        if (!$cfg['ad_enabled'] && !$dev_bypass) {
            throw new ApiException('CEPING_AD_NOT_CONFIGURED');
        }

        $log = (new UnlockLog())->where('record_id', $record_id)
            ->where('ad_type', $ad_type)->findOrEmpty();
        if ($log->isEmpty()) {
            $log = (new UnlockLog())->create([
                'site_id'       => $this->site_id,
                'member_id'     => $this->member_id,
                'record_id'     => $record_id,
                'test_id'       => $record->test_id,
                'ad_type'       => $ad_type,
                'ad_unit_id'    => (string)$cfg['ad_unit_id'],
                'verify_status' => 1,
            ]);
        }
        return [
            'unlocked'       => true,
            'record_id'      => $record_id,
            'unlock_id'      => (int)$log->id,
            'dev_bypass'     => $dev_bypass,
        ];
    }
}
