<?php

namespace addon\ceping\app\api\controller;

use addon\ceping\app\service\api\TestService;
use core\base\BaseApiController;

class Test extends BaseApiController
{
    /**
     * 分类页列表
     */
    public function category()
    {
        $data = $this->request->params([['category', '']]);
        return success((new TestService())->category($data['category']));
    }

    /**
     * 测评搜索（关键字为空时返回热门推荐）
     */
    public function search()
    {
        $data = $this->request->params([['keyword', '']]);
        return success((new TestService())->search((string)$data['keyword']));
    }

    /**
     * 测试介绍页
     */
    public function intro()
    {
        $data = $this->request->params([['test_id', 0]]);
        return success((new TestService())->intro((int)$data['test_id']));
    }

    /**
     * 试卷
     */
    public function paper()
    {
        $data = $this->request->params([['test_id', 0]]);
        return success((new TestService())->paper((int)$data['test_id']));
    }
}
