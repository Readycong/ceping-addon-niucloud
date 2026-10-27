<?php

namespace addon\ceping\app\adminapi\controller;

use addon\ceping\app\service\admin\TestService;
use core\base\BaseAdminController;

class Test extends BaseAdminController
{
    /**
     * 题库列表
     */
    public function lists()
    {
        $data = $this->request->params([
            ['category', ''],
            ['scoring_model', ''],
            ['copyright_status', ''],
            ['online_status', ''],
            ['keyword', ''],
        ]);
        return success((new TestService())->getPage($data));
    }

    /**
     * 题库详情（只读全量题目/结果）
     */
    public function info()
    {
        $data = $this->request->params([['id', 0]]);
        return success((new TestService())->getInfo((int)$data['id']));
    }

    /**
     * 上下线
     */
    public function setOnline()
    {
        $data = $this->request->params([['id', 0], ['online_status', 1]]);
        return success((new TestService())->setOnline((int)$data['id'], (int)$data['online_status']));
    }

    /**
     * 热门标记
     */
    public function setHot()
    {
        $data = $this->request->params([['id', 0], ['is_hot', 0]]);
        return success((new TestService())->setHot((int)$data['id'], (int)$data['is_hot']));
    }

    /**
     * 排序
     */
    public function setSort()
    {
        $data = $this->request->params([['id', 0], ['sort', 0]]);
        return success((new TestService())->setSort((int)$data['id'], (int)$data['sort']));
    }
}
