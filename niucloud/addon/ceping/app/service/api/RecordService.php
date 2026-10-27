<?php

namespace addon\ceping\app\service\api;

use addon\ceping\app\model\AiReport;
use addon\ceping\app\model\Record;
use addon\ceping\app\model\Test;
use addon\ceping\app\model\UnlockLog;
use addon\ceping\app\service\core\CepingConfigService;
use addon\ceping\app\service\core\ScoringService;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Db;

/**
 * 测评记录服务（提交/列表/详情/删除/清空）
 */
class RecordService extends BaseApiService
{
    /**
     * 提交作答：服务端独立复核计分后落库
     */
    public function submit(int $test_id, array $answers, string $source = 'home'): array
    {
        $test = (new Test())->where('site_id', $this->site_id)
            ->where('test_id', $test_id)->where('online_status', 1)->findOrEmpty();
        if ($test->isEmpty()) throw new ApiException('CEPING_TEST_NOT_FOUND');

        $scored = (new ScoringService())->score($test->toArray(), $answers);

        $record = (new Record())->create([
            'site_id'      => $this->site_id,
            'member_id'    => $this->member_id,
            'test_id'      => $test_id,
            'mode'         => 'single',
            'answers'      => array_values($answers),
            'score_detail' => $scored['score_detail'],
            'result_key'   => $scored['result_key'],
            'result_name'  => $scored['result_name'],
            'total_score'  => $scored['total_score'],
            'source'       => $source,
            'status'       => 'completed',
        ]);

        return $this->detail((int)$record->id);
    }

    /**
     * 历史列表
     */
    public function records(array $filter = []): array
    {
        $test_id = (int)($filter['test_id'] ?? 0);
        $query = (new Record())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)
            ->where('mode', 'single')
            ->when($test_id > 0, fn($q) => $q->where('test_id', $test_id))
            ->order('create_time desc,id desc');
        // 注意：Paginator::each 对模型对象不会用闭包返回值替换元素，需手工映射
        $p = $this->getPageParam();
        $paginator = $query->paginate(['list_rows' => $p['limit'], 'page' => $p['page']]);
        $map = $this->testMap();
        $data = array_map(function ($record) use ($map) {
            $test = $map[$record->test_id] ?? null;
            return [
                'record_id'     => (int)$record->id,
                'test_id'       => (int)$record->test_id,
                'display_name'  => $test['display_name'] ?? '',
                'short_name'    => $test['short_name'] ?? '',
                'cover'         => $test['cover'] ?? '',
                'theme_key'     => $test['theme_key'] ?? '',
                'result_key'    => $record->result_key,
                'result_name'   => $record->result_name,
                'total_score'   => (int)$record->total_score,
                'create_time'   => $this->rawTime($record->getOrigin('create_time')),
            ];
        }, $paginator->items());
        return array_merge($paginator->toArray(), ['data' => $data]);
    }

    /**
     * create_time 统一为秒级时间戳：兼容框架自动格式化的日期字符串与原始 int
     */
    protected function rawTime($value): int
    {
        if (is_numeric($value)) return (int)$value;
        $t = strtotime((string)$value);
        return $t === false ? 0 : $t;
    }

    /**
     * 记录详情（含完整结果文案）
     */
    public function detail(int $record_id): array
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
        if ($result && !empty($result['shareCard'])) {
            if (is_array($result['shareCard'])) {
                $result['shareCard']['cover'] = $test->cover;
            } else {
                $result['shareCard'] = ['text' => (string)$result['shareCard'], 'cover' => $test->cover];
            }
        }

        $unlocked = (new UnlockLog())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)->where('record_id', $record_id)->count() > 0;

        $ai_report = (new AiReport())->where('record_id', $record_id)->findOrEmpty();
        $ai = [
            'enabled' => (new CepingConfigService())->getPublicConfig($this->site_id)['ai_enabled'],
            'unlocked' => $unlocked,
            'status'   => $ai_report->isEmpty() ? 'none' : $ai_report->status,
            'content'  => (!$ai_report->isEmpty() && $ai_report->status === 'ready') ? $ai_report->content : null,
        ];

        $pair = null;
        if (in_array($record->mode, ['partner_inviter', 'partner_invitee'])) {
            $pair = $this->pairDetail($record);
        }

        return [
            'record_id'     => (int)$record->id,
            'test_id'       => (int)$record->test_id,
            'display_name'  => $test->display_name,
            'short_name'    => $test->short_name,
            'cover'         => $test->cover,
            'theme_key'     => $test->theme_key,
            'scoring_model' => $test->scoring_model,
            'mode'          => $record->mode,
            'status'        => $record->status,
            'answers'       => $record->answers ?: [],
            'score_detail'  => $record->score_detail ?: [],
            'result_key'    => $record->result_key,
            'result_name'   => $record->result_name,
            'total_score'   => (int)$record->total_score,

            'result'        => $result,
            'chart'         => $this->chartMeta($test),
            'compliance'    => $test->compliance ?: [],
            'ai'            => $ai,
            'pair'          => $pair,
            'create_time'   => $this->rawTime($record->getOrigin('create_time')),
        ];
    }

    /**
     * 结果分布图所需的展示元数据（不泄露计分判定，仅名称/区间/标签）
     */
    protected function chartMeta(Test $test): array
    {
        switch ($test->scoring_model) {
            case 'category':
                return [
                    'model' => 'category',
                    'categories' => array_map(fn($c) => [
                        'key' => $c['key'], 'name' => $c['name'], 'color' => $c['colorHex'] ?? '',
                    ], $test->categories ?: []),
                ];
            case 'threshold':
                return [
                    'model' => 'threshold',
                    'max_points' => (int)($test->questions ? count($test->questions) * 3 : 0),
                    'bands' => array_map(fn($b) => [
                        'key' => $b['key'], 'name' => $b['name'], 'min' => (int)$b['min'], 'max' => (int)$b['max'],
                    ], $test->bands ?: []),
                ];
            case 'dimension':
                return [
                    'model' => 'dimension',
                    'axes' => array_map(fn($a) => [
                        'key' => $a['key'], 'name' => $a['name'],
                        'poles' => array_map(fn($p) => ['key' => $p['key'], 'label' => $p['label']], $a['poles']),
                    ], $test->axes ?: []),
                ];
            default:
                return ['model' => $test->scoring_model];
        }
    }

    protected function pairDetail(Record $record): ?array
    {
        $invite = Db::name('ceping_partner_invite')->where('id', $record->invite_id)->find();
        if (!$invite) return null;
        $other_mode = $record->mode === 'partner_inviter' ? 'partner_invitee' : 'partner_inviter';
        $other = (new Record())->where('invite_id', $record->invite_id)
            ->where('mode', $other_mode)->findOrEmpty();
        return [
            'status'           => $invite['status'],
            'token'            => $invite['token'],
            'is_inviter'       => $record->mode === 'partner_inviter',
            'partner_nickname' => null,
            'score_detail'     => $record->score_detail,
            'partner_record_id' => $other->isEmpty() ? 0 : (int)$other->id,
        ];
    }

    public function delete(int $record_id): bool
    {
        $record = (new Record())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)->where('id', $record_id)->findOrEmpty();
        if ($record->isEmpty()) throw new ApiException('CEPING_RECORD_NOT_FOUND');
        $record->delete();
        return true;
    }

    public function clear(): bool
    {
        (new Record())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)->where('mode', 'single')->delete();
        return true;
    }

    protected function testMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            $tests = (new Test())->where('site_id', $this->site_id)->field('test_id,display_name,short_name,cover,theme_key')->select();
            foreach ($tests as $t) {
                $map[(int)$t['test_id']] = $t->toArray();
            }
        }
        return $map;
    }
}
