<?php

namespace addon\ceping\app\service\api;

use addon\ceping\app\model\AiReport;
use addon\ceping\app\model\Record;
use addon\ceping\app\model\Test;
use addon\ceping\app\model\UnlockLog;
use addon\ceping\app\service\core\AiReportService as CoreAiReportService;
use addon\ceping\app\service\core\CepingConfigService;
use core\base\BaseApiService;
use core\exception\ApiException;

/**
 * AI深度报告（请求/轮询）
 */
class AiReportService extends BaseApiService
{
    protected function loadContext(int $record_id): array
    {
        $record = (new Record())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)->where('id', $record_id)->findOrEmpty();
        if ($record->isEmpty()) throw new ApiException('CEPING_RECORD_NOT_FOUND');

        $test = (new Test())->where('site_id', $this->site_id)
            ->where('test_id', $record->test_id)->findOrEmpty();
        $result = null;
        foreach (($test->results ?: []) as $r) {
            if ($r['key'] === $record->result_key) {
                $result = $r;
                break;
            }
        }
        return [$record, $test, $result];
    }

    /**
     * 请求生成（同步执行；队列关闭环境下立即出结果或失败）
     */
    public function request(int $record_id): array
    {
        $cfg_service = new CepingConfigService();
        $cfg = $cfg_service->getConfig($this->site_id);
        if (empty($cfg['ai_enabled']) || empty($cfg['ai_base_url']) || empty($cfg['ai_model'])) {
            throw new ApiException('CEPING_AI_NOT_CONFIGURED');
        }

        // 广告门：广告开启时需解锁；未开启时不拦截（后台控制成本）
        if (!empty($cfg['ad_enabled'])) {
            $unlocked = (new UnlockLog())->where('site_id', $this->site_id)
                ->where('member_id', $this->member_id)->where('record_id', $record_id)->count() > 0;
            if (!$unlocked) throw new ApiException('CEPING_AD_LOCKED');
        }

        [$record, $test, $result] = $this->loadContext($record_id);

        $report = (new AiReport())->where('record_id', $record_id)->findOrEmpty();
        if ($report->isEmpty() || $report->status === 'failed') {
            if ($report->isEmpty()) {
                $report = (new AiReport())->create([
                    'site_id'   => $this->site_id,
                    'member_id' => $this->member_id,
                    'record_id' => $record_id,
                    'test_id'   => $record->test_id,
                    'result_key' => $record->result_key,
                    'status'    => 'pending',
                ]);
            } else {
                $report->save(['status' => 'pending', 'fail_reason' => '']);
            }

            $ctx = [
                'site_id'      => $this->site_id,
                'member_id'    => $this->member_id,
                'record_id'    => $record_id,
                'test_id'      => (int)$record->test_id,
                'result_key'   => $record->result_key,
                'result'       => $result,
                'answers'      => $record->answers ?: [],
                'score_detail' => $record->score_detail ?: [],
                'display_name' => $test->display_name,
            ];
            (new CoreAiReportService())->generate($cfg, $ctx, $report);
            $report = (new AiReport())->find($report->id);
        }

        return $this->present($report);
    }

    /**
     * 轮询
     */
    public function poll(int $record_id): array
    {
        [$record] = $this->loadContext($record_id);
        $report = (new AiReport())->where('record_id', $record_id)->findOrEmpty();
        if ($report->isEmpty()) {
            return ['status' => 'none', 'content' => null, 'fail_reason' => ''];
        }
        return $this->present($report);
    }

    protected function present(AiReport $report): array
    {
        return [
            'status'      => $report->status,
            'content'     => $report->status === 'ready' ? $report->content : null,
            'is_cached'   => (int)$report->is_cached,
            'is_safety'   => (int)$report->is_safety,
            'model'       => $report->model,
            'fail_reason' => $report->fail_reason,
        ];
    }
}
