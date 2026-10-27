<?php

namespace addon\ceping\app\service\admin;

use addon\ceping\app\model\AiReport;
use addon\ceping\app\model\Record;
use addon\ceping\app\model\Test;
use addon\ceping\app\model\UnlockLog;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 后台测评记录查看
 */
class RecordService extends BaseAdminService
{
    public function getPage(array $search = []): array
    {
        $query = (new Record())->where('site_id', $this->site_id)
            ->withSearch(['member_id', 'test_id', 'mode', 'create_time'], $search)
            ->order('id desc');
        // Paginator::each 对模型对象不会替换闭包返回值，手工映射
        $p = $this->getPageParam();
        $paginator = $query->paginate(['list_rows' => $p['limit'], 'page' => $p['page']]);
        $data = array_map(function ($item) {
            $v = $item->getOrigin('create_time');
            return [
                'record_id'    => (int)$item->id,
                'member_id'    => (int)$item->member_id,
                'test_id'      => (int)$item->test_id,
                'mode'         => $item->mode,
                'result_key'   => $item->result_key,
                'result_name'  => $item->result_name,
                'total_score'  => (int)$item->total_score,
                'source'       => $item->source,
                'status'       => $item->status,
                'create_time'  => is_numeric($v) ? (int)$v : (strtotime((string)$v) ?: 0),
            ];
        }, $paginator->items());
        return array_merge($paginator->toArray(), ['data' => $data]);
    }

    public function getInfo(int $id): array
    {
        $record = (new Record())->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty();
        if ($record->isEmpty()) throw new AdminException('CEPING_RECORD_NOT_FOUND');

        $test = (new Test())->where('site_id', $this->site_id)
            ->where('test_id', $record->test_id)->field('test_id,display_name,cover,theme_key,scoring_model')->findOrEmpty();
        $ai = (new AiReport())->where('record_id', $id)->findOrEmpty();
        $unlock = (new UnlockLog())->where('record_id', $id)->select();

        return [
            'record'       => $record->toArray(),
            'test'         => $test->toArray(),
            'ai_report'    => $ai->isEmpty() ? null : ['status' => $ai->status, 'content' => $ai->content, 'fail_reason' => $ai->fail_reason],
            'unlock_logs'  => $unlock->toArray(),
        ];
    }
}
