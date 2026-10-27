<?php

namespace addon\ceping\app\service\admin;

use addon\ceping\app\model\Test;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 后台题库管理
 */
class TestService extends BaseAdminService
{
    public function getPage(array $search = []): array
    {
        $model = new Test();
        $query = $model->where('site_id', $this->site_id)->withSearch(
            ['category', 'scoring_model', 'copyright_status', 'online_status', 'keyword'],
            $search
        )->order('sort desc, test_id asc');
        // Paginator::each 对模型对象不会替换闭包返回值，手工映射
        $p = $this->getPageParam();
        $paginator = $query->paginate(['list_rows' => $p['limit'], 'page' => $p['page']]);
        $data = array_map(function ($item) {
            $v = $item->getOrigin('create_time');
            return [
                'id'               => (int)$item->id,
                'test_id'          => (int)$item->test_id,
                'title'            => $item->title,
                'display_name'     => $item->display_name,
                'short_name'       => $item->short_name,
                'category'         => $item->category,
                'theme_key'        => $item->theme_key,
                'cover'            => $item->cover,
                'scoring_model'    => $item->scoring_model,
                'question_count'   => (int)$item->question_count,
                'result_count'     => (int)$item->result_count,
                'est_minutes'      => (int)$item->est_minutes,
                'copyright_status' => $item->copyright_status,
                'online_status'    => (int)$item->online_status,
                'is_hot'           => (int)$item->is_hot,
                'sort'             => (int)$item->sort,
                'create_time'      => is_numeric($v) ? (int)$v : (strtotime((string)$v) ?: 0),
            ];
        }, $paginator->items());
        return array_merge($paginator->toArray(), ['data' => $data]);
    }

    public function getInfo(int $id): array
    {
        $test = $this->find($id);
        $data = $test->toArray();
        // 后台只读全量结构
        $data['questions'] = $test->questions;
        $data['results'] = $test->results;
        return $data;
    }

    public function setOnline(int $id, int $status): bool
    {
        $this->find($id)->save(['online_status' => $status ? 1 : 0]);
        return true;
    }

    public function setHot(int $id, int $is_hot): bool
    {
        $this->find($id)->save(['is_hot' => $is_hot ? 1 : 0]);
        return true;
    }

    public function setSort(int $id, int $sort): bool
    {
        $this->find($id)->save(['sort' => $sort]);
        return true;
    }

    protected function find(int $id): Test
    {
        $test = (new Test())->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty();
        if ($test->isEmpty()) throw new AdminException('CEPING_TEST_NOT_FOUND');
        return $test;
    }
}
