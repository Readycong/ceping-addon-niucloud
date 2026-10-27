<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\RecordService;
use core\base\BaseApiController;

class Record extends BaseApiController
{
    /**
     * 提交作答（服务端复核计分）
     */
    public function submit()
    {
        $data = $this->request->params([
            ['test_id', 0],
            ['answers', []],
            ['source', 'home'],
        ]);
        return success((new RecordService())->submit((int)$data['test_id'], $data['answers'], $data['source']));
    }

    /**
     * 历史记录
     */
    public function records()
    {
        $data = $this->request->params([['test_id', 0]]);
        return success((new RecordService())->records(['test_id' => (int)$data['test_id']]));
    }

    /**
     * 记录详情
     */
    public function detail()
    {
        $data = $this->request->params([['record_id', 0]]);
        return success((new RecordService())->detail((int)$data['record_id']));
    }

    /**
     * 删除记录
     */
    public function delete()
    {
        $data = $this->request->params([['record_id', 0]]);
        return success((new RecordService())->delete((int)$data['record_id']));
    }

    /**
     * 清空历史
     */
    public function clear()
    {
        return success((new RecordService())->clear());
    }
}
