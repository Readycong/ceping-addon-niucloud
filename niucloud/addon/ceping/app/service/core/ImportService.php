<?php

namespace addon\ceping\app\service\core;

use addon\ceping\app\model\Test;
use core\base\BaseCoreService;

/**
 * 题库导入服务（dry-run / upsert，可重复执行去重）
 */
class ImportService extends BaseCoreService
{
    // 8大金刚区归类（PRD §6.1，以选题编号为准）
    public const CATEGORY_MAP = [
        'persona'  => [1, 3, 10, 12, 18, 48],
        'love'     => [2, 7, 11, 23, 37, 47],
        'office'   => [5, 13, 20, 22, 31, 36, 39, 46],
        'social'   => [9, 15, 21, 32, 38, 41],
        'emotion'  => [8, 16, 19, 26, 33, 40],
        'life'     => [24, 25, 27, 28, 29, 43, 44, 45, 49],
        'aesthetic'=> [6, 17, 30, 34, 42],
        'mystery'  => [4, 14, 35, 50],
    ];

    // 50选题主题包（PRD 附录A）
    public const THEME_MAP = [
        1 => 'persona-archive', 2 => 'love-letter', 3 => 'persona-archive', 4 => 'tarot-night',
        5 => 'office-comic', 6 => 'gallery-aesthetic', 7 => 'love-letter', 8 => 'wave-healing',
        9 => 'chat-bubble', 10 => 'persona-archive', 11 => 'love-letter', 12 => 'persona-archive',
        13 => 'office-comic', 14 => 'tarot-night', 15 => 'chat-bubble', 16 => 'wave-healing',
        17 => 'gallery-aesthetic', 18 => 'persona-archive', 19 => 'wave-healing', 20 => 'office-comic',
        21 => 'chat-bubble', 22 => 'office-comic', 23 => 'love-letter', 24 => 'zine-collage',
        25 => 'zine-collage', 26 => 'wave-healing', 27 => 'zine-collage', 28 => 'zine-collage',
        29 => 'zine-collage', 30 => 'gallery-aesthetic', 31 => 'office-comic', 32 => 'chat-bubble',
        33 => 'wave-healing', 34 => 'gallery-aesthetic', 35 => 'tarot-night', 36 => 'office-comic',
        37 => 'love-letter', 38 => 'chat-bubble', 39 => 'office-comic', 40 => 'wave-healing',
        41 => 'chat-bubble', 42 => 'gallery-aesthetic', 43 => 'zine-collage', 44 => 'zine-collage',
        45 => 'zine-collage', 46 => 'office-comic', 47 => 'love-letter', 48 => 'persona-archive',
        49 => 'zine-collage', 50 => 'tarot-night',
    ];

    public const HOT_IDS = [5, 9, 11, 7, 2, 24, 1, 50];

    protected string $bankDir;

    public function __construct()
    {
        parent::__construct();
        $candidates = [
            dirname(root_path()) . '/PRD/题库',
            root_path() . '../PRD/题库',
            root_path() . '../../PRD/题库',
        ];
        $this->bankDir = $candidates[0];
        foreach ($candidates as $dir) {
            $real = realpath($dir);
            if ($real && is_dir($real)) {
                $this->bankDir = $real;
                break;
            }
        }
    }

    public function getBankDir(): string
    {
        return $this->bankDir;
    }

    public function categoryOf(int $test_id): string
    {
        foreach (self::CATEGORY_MAP as $cat => $ids) {
            if (in_array($test_id, $ids)) return $cat;
        }
        return 'persona';
    }

    public function themeOf(int $test_id): string
    {
        return self::THEME_MAP[$test_id] ?? 'persona-archive';
    }

    /**
     * 去除标题版权前缀，如「【商业版权·原题参考】16型人格速测（MBTI 精简版）」
     */
    public function displayName(string $title): string
    {
        return trim(preg_replace('/^【[^】]*】/u', '', $title));
    }

    /**
     * 装载全部题库JSON并校验
     * @return array{items:array,issues:array}
     */
    public function collect(): array
    {
        $index_file = $this->bankDir . '/INDEX.json';
        if (!is_file($index_file)) {
            return ['items' => [], 'issues' => [['level' => 'error', 'msg' => 'INDEX.json 不存在: ' . $index_file]]];
        }
        $index = json_decode(file_get_contents($index_file), true);
        $meta_list = $index['tests'] ?? $index['items'] ?? (isset($index[0]) ? $index : []);

        $items = [];
        $issues = [];
        foreach ($meta_list as $meta) {
            $id = (int)$meta['id'];
            $path = $this->bankDir . '/' . $meta['file'];
            if (!is_file($path)) {
                $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "文件缺失: {$meta['file']}"];
                continue;
            }
            $data = json_decode(file_get_contents($path), true);
            if (!$data) {
                $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "JSON解析失败: {$meta['file']}"];
                continue;
            }
            $data['_file'] = $meta['file'];
            $this->validateItem($data, $issues);
            $items[$id] = $data;
        }

        // 编号完整性
        for ($i = 1; $i <= 50; $i++) {
            if (!isset($items[$i])) $issues[] = ['level' => 'error', 'id' => $i, 'msg' => '选题编号缺失'];
        }
        ksort($items);
        return ['items' => array_values($items), 'issues' => $issues];
    }

    protected function validateItem(array $data, array &$issues): void
    {
        $id = (int)$data['id'];
        $model = $data['scoringModel'] ?? '';
        $questions = $data['questions'] ?? [];
        $results = $data['results'] ?? [];
        $declared = (int)($data['questionCount'] ?? 0);

        if (count($questions) === 0) {
            $issues[] = ['level' => 'error', 'id' => $id, 'msg' => '#' . $id . ' 题目为空（待自编衍生）'];
        } elseif (count($questions) < 10 && $id !== 46) {
            $issues[] = ['level' => 'warn', 'id' => $id, 'msg' => '#' . $id . ' 题量不足10题（' . count($questions) . '题），平票概率偏高'];
        }
        if ($id === 46 && count($questions) < 10) {
            $issues[] = ['level' => 'warn', 'id' => $id, 'msg' => '#46 贝尔宾团队角色题量为' . count($questions) . '题，9结果平票风险'];
        }
        if (count($questions) !== $declared) {
            $issues[] = ['level' => 'warn', 'id' => $id, 'msg' => "#$id 实际题数" . count($questions) . "与questionCount($declared)不一致，将按实际题数导入"];
        }

        $cat_keys = array_column($data['categories'] ?? [], 'key');
        $band_keys = array_column($data['bands'] ?? [], 'key');
        $result_keys = array_column($results, 'key');

        foreach ($questions as $q) {
            $opts = $q['options'] ?? [];
            if (count($opts) !== 4) {
                $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 第{$q['no']}题选项数=" . count($opts) . '（须4个）'];
            }
            $keys = array_column($opts, 'key');
            if ($keys !== ['A', 'B', 'C', 'D']) {
                $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 第{$q['no']}题选项key非A/B/C/D"];
            }
            foreach ($opts as $opt) {
                $score = $opt['score'] ?? [];
                if ($model === 'category' && empty($score['cat'])) {
                    $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 第{$q['no']}题选项{$opt['key']}缺少cat"];
                }
                if ($model === 'dimension' && (empty($score['axis']) || !isset($score['pole']))) {
                    $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 第{$q['no']}题选项{$opt['key']}缺少axis/pole"];
                }
                if ($model === 'threshold' && !isset($score['points'])) {
                    $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 第{$q['no']}题选项{$opt['key']}缺少points"];
                }
                if ($model === 'category' && !empty($cat_keys) && !empty($score['cat']) && !in_array($score['cat'], $cat_keys)) {
                    $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 第{$q['no']}题cat={$score['cat']}不在categories"];
                }
            }
        }

        if ($model === 'category') {
            foreach ($results as $r) {
                if (empty($r['categoryKey'])) {
                    $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 结果{$r['key']}缺少categoryKey"];
                } elseif (!in_array($r['categoryKey'], $cat_keys)) {
                    $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 结果{$r['key']}的categoryKey无效"];
                }
            }
        }
        if ($model === 'threshold') {
            if (empty($data['bands'])) {
                $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id threshold模型缺少bands"];
            } else {
                foreach ($results as $r) {
                    if (!in_array($r['key'], $band_keys)) {
                        $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 结果{$r['key']}与bands不对应"];
                    }
                }
                if ($id === 7) {
                    $max = max(array_column($data['bands'], 'max'));
                    if ($max != 30) $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#7 双人题bands上界应为30，实际$max"];
                }
            }
        }
        if ($model === 'dimension') {
            $axis_keys = array_column($data['axes'] ?? [], 'key');
            foreach ($results as $r) {
                foreach (($r['match'] ?? []) as $ak => $pole) {
                    if ($pole !== '*' && !in_array($ak, $axis_keys)) {
                        $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 结果{$r['key']}match含未知轴$ak"];
                    }
                }
            }
            foreach ($questions as $q) {
                foreach ($q['options'] as $opt) {
                    $sc = $opt['score'] ?? [];
                    if (!empty($sc['axis']) && in_array($sc['axis'], $axis_keys)) {
                        $axis = null;
                        foreach ($data['axes'] as $ax) if ($ax['key'] === $sc['axis']) $axis = $ax;
                        if ($axis && !in_array($sc['pole'], array_column($axis['poles'], 'key'))) {
                            $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id 第{$q['no']}题pole无效"];
                        }
                    }
                }
            }
        }

        // 合规字段
        $compliance = $data['compliance'] ?? [];
        if (empty($compliance['nonDiagnostic'])) {
            $issues[] = ['level' => 'warn', 'id' => $id, 'msg' => "#$id 缺少compliance.nonDiagnostic非诊断声明"];
        }
        foreach ($results as $r) {
            foreach (['summary', 'detail', 'growthTip', 'shareCard'] as $field) {
                if (!isset($r[$field])) {
                    $issues[] = ['level' => 'warn', 'id' => $id, 'msg' => "#$id 结果{$r['key']}缺少字段:$field"];
                }
            }
        }
        if (count($result_keys) !== count(array_unique($result_keys))) {
            $issues[] = ['level' => 'error', 'id' => $id, 'msg' => "#$id results.key存在重复"];
        }
    }

    /**
     * dry-run
     */
    public function dryRun(int $site_id = 1000): array
    {
        $collected = $this->collect();
        $exist_map = (new Test())->where('site_id', $site_id)->column('test_id');
        $rows = [];
        foreach ($collected['items'] as $item) {
            $id = (int)$item['id'];
            $rows[] = [
                'test_id'        => $id,
                'display_name'   => $this->displayName($item['title']),
                'category'       => $this->categoryOf($id),
                'theme_key'      => $this->themeOf($id),
                'scoring_model'  => $item['scoringModel'],
                'question_count' => count($item['questions']),
                'result_count'   => count($item['results']),
                'is_new'         => !in_array($id, $exist_map),
                'copyright'      => $item['copyrightStatus'] ?? 'internet_native',
                'is_hot'         => in_array($id, self::HOT_IDS),
            ];
        }
        return [
            'bank_dir' => $this->bankDir,
            'total'    => count($rows),
            'new_count' => count(array_filter($rows, fn($r) => $r['is_new'])),
            'update_count' => count(array_filter($rows, fn($r) => !$r['is_new'])),
            'rows'     => $rows,
            'issues'   => $collected['issues'],
        ];
    }

    /**
     * 正式导入（upsert，保护运营字段）
     */
    public function import(int $site_id = 1000, bool $ignore_warn = true): array
    {
        $collected = $this->collect();
        $errors = array_filter($collected['issues'], fn($i) => $i['level'] === 'error');
        if (!$ignore_warn && !empty($collected['issues'])) {
            throw new \RuntimeException('存在校验问题，已中止: ' . json_encode($collected['issues'], JSON_UNESCAPED_UNICODE));
        }
        if (!empty($errors)) {
            throw new \RuntimeException('存在error级问题，已中止: ' . json_encode(array_values($errors), JSON_UNESCAPED_UNICODE));
        }

        $insert = 0;
        $update = 0;
        $model = new Test();
        foreach ($collected['items'] as $item) {
            $id = (int)$item['id'];
            $payload = [
                'site_id'        => $site_id,
                'test_id'        => $id,
                'title'          => $item['title'],
                'short_name'     => $item['shortName'] ?? $this->displayName($item['title']),
                'display_name'   => $this->displayName($item['title']),
                'category'       => $this->categoryOf($id),
                'theme_key'      => $this->themeOf($id),
                'tags'           => $item['tags'] ?? [],
                'scoring_model'  => $item['scoringModel'],
                'question_count' => count($item['questions']),
                'result_count'   => count($item['results']),
                'est_minutes'    => (int)($item['estimatedMinutes'] ?? 3),
                'description'    => $item['description'] ?? '',
                'instructions'   => is_array($item['instructions'] ?? null) ? implode("\n", $item['instructions']) : ($item['instructions'] ?? ''),
                'tie_break'      => $item['tieBreak'] ?? '',
                'axes'           => $item['axes'] ?? null,
                'categories'     => $item['categories'] ?? null,
                'bands'          => $item['bands'] ?? null,
                'questions'      => $item['questions'],
                'results'        => $item['results'],
                'compliance'     => $item['compliance'] ?? [],
                'copyright_status' => $item['copyrightStatus'] ?? 'internet_native',
                'bank_version'   => '1.0',
            ];

            $exist = $model->where('site_id', $site_id)->where('test_id', $id)->findOrEmpty();
            if ($exist->isEmpty()) {
                $payload['online_status'] = 1;
                $payload['is_hot'] = in_array($id, self::HOT_IDS) ? 1 : 0;
                $payload['sort'] = 51 - $id;
                $payload['cover'] = '';
                $model->create($payload);
                $insert++;
            } else {
                // 保护运营字段：online_status / is_hot / sort / cover
                unset($payload['online_status'], $payload['is_hot'], $payload['sort'], $payload['cover']);
                $exist->save($payload);
                $update++;
            }
        }
        return ['insert' => $insert, 'update' => $update, 'total' => count($collected['items']), 'issues' => $collected['issues']];
    }
}
