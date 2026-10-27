<?php

namespace addon\ceping\app\service\api;

use addon\ceping\app\model\Record;
use addon\ceping\app\model\Test;
use addon\ceping\app\service\core\CepingConfigService;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Cache;

/**
 * 前台题库浏览服务
 */
class TestService extends BaseApiService
{
    const CATEGORIES = [
        ['key' => 'persona',   'name' => '人格认知', 'emoji' => '🎭'],
        ['key' => 'love',      'name' => '情感关系', 'emoji' => '💌'],
        ['key' => 'office',    'name' => '职场成长', 'emoji' => '💼'],
        ['key' => 'social',    'name' => '社交人际', 'emoji' => '💬'],
        ['key' => 'emotion',   'name' => '情绪状态', 'emoji' => '🌊'],
        ['key' => 'life',      'name' => '生活消费', 'emoji' => '🍲'],
        ['key' => 'aesthetic', 'name' => '兴趣审美', 'emoji' => '🎨'],
        ['key' => 'mystery',   'name' => '玄学脑洞', 'emoji' => '🔮'],
    ];

    /**
     * 首页聚合
     */
    public function home(): array
    {
        $model = new Test();
        $base = fn() => $model->where('site_id', $this->site_id)->where('online_status', 1);

        // 注意：Collection::each 对模型对象不会用闭包返回值替换元素，必须用 array_map 映射
        $hot_tests = array_map(fn($t) => $this->card($t), (clone $base())->where('is_hot', 1)
            ->order('sort desc, test_id asc')->limit(8)->select()->all());
        $latest = array_map(fn($t) => $this->card($t), (clone $base())
            ->order('sort desc, test_id asc')->limit(12)->select()->all());

        return [
            'categories' => self::CATEGORIES,
            'hot'        => $hot_tests,
            'tests'      => $latest,
            'config'     => (new CepingConfigService())->getPublicConfig($this->site_id),
        ];
    }

    public function categories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * 测评搜索
     * - keyword 为空：返回 10 个热门测评（不足时用其他上线测评补齐）
     * - keyword 非空：输入整串 + 拆词关键字混合 OR 检索，按匹配度倒序最多 10 条
     */
    public function search(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return ['keyword' => '', 'tests' => $this->hotList(10)];
        }

        // 输入长度保护（按字符计）
        if (mb_strlen($keyword) > 30) {
            $keyword = mb_substr($keyword, 0, 30);
        }

        // 题库仅 50 条，直接取出上线测评在 PHP 侧做精细匹配与打分
        $tests = (new Test())->where('site_id', $this->site_id)
            ->where('online_status', 1)
            ->field('test_id,title,short_name,display_name,category,theme_key,cover,tags,question_count,est_minutes,description,is_hot,sort')
            ->order('sort desc, test_id asc')
            ->select()->all();

        [$terms, $bigrams] = $this->extractKeywords($keyword);
        $raw = mb_strtolower($keyword);

        $scored = [];
        foreach ($tests as $t) {
            $score = $this->matchScore($t, $raw, $terms, $bigrams);
            if ($score > 0) {
                $scored[] = ['test' => $t, 'score' => $score];
            }
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
            if ((int)$a['test']->is_hot !== (int)$b['test']->is_hot) {
                return (int)$b['test']->is_hot <=> (int)$a['test']->is_hot;
            }
            if ((int)$a['test']->sort !== (int)$b['test']->sort) {
                return (int)$b['test']->sort <=> (int)$a['test']->sort;
            }
            return (int)$a['test']->test_id <=> (int)$b['test']->test_id;
        });

        $scored = array_slice($scored, 0, 10);
        return [
            'keyword' => $keyword,
            'tests'   => array_map(fn($item) => $this->card($item['test']), $scored),
        ];
    }

    /**
     * 热门测评列表（不足 limit 时按排序补齐）
     */
    protected function hotList(int $limit): array
    {
        $model = new Test();
        $hots = $model->where('site_id', $this->site_id)->where('online_status', 1)
            ->where('is_hot', 1)->order('sort desc, test_id asc')->limit($limit)->select()->all();

        if (count($hots) < $limit) {
            $existIds = array_map(fn($t) => (int)$t->test_id, $hots);
            $more = (new Test())->where('site_id', $this->site_id)->where('online_status', 1)
                ->whereNotIn('test_id', $existIds ?: [0])
                ->order('sort desc, test_id asc')->limit($limit - count($hots))->select()->all();
            $hots = array_merge($hots, $more);
        }

        return array_map(fn($t) => $this->card($t), $hots);
    }

    /**
     * 拆词：提取拉丁/数字词、中文片段，并为中文片段生成二元字组作为关键字
     * 例："MBTI 恋爱人格" => terms:[mbti,恋爱,人格...按词], bigrams:[恋爱,爱人,人格]
     * @return array{0:string[],1:string[]} [terms, bigrams]
     */
    protected function extractKeywords(string $keyword): array
    {
        $raw = mb_strtolower($keyword);
        $terms = [];
        $bigrams = [];

        // 拆出 拉丁/数字串 与 中文串
        if (!preg_match_all('/[a-z0-9]+|[\x{4e00}-\x{9fff}]+/u', $raw, $m)) {
            return [[], []];
        }

        foreach ($m[0] as $part) {
            if ($part === '') continue;
            $terms[$part] = true;
            // 中文串额外按空白相邻语义切二元字组，支持输入串重新组合的 OR 命中
            if (preg_match('/^[\x{4e00}-\x{9fff}]+$/u', $part) && mb_strlen($part) >= 2) {
                $len = mb_strlen($part);
                for ($i = 0; $i < $len - 1; $i++) {
                    $bigrams[mb_substr($part, $i, 2)] = true;
                }
            }
        }

        // 按空格/标点切分出的独立词也作为关键字（已被上面正则覆盖的不重复加入）
        return [array_keys($terms), array_keys($bigrams)];
    }

    /**
     * 匹配打分：0 表示未命中；分值越大匹配度越高
     */
    protected function matchScore(Test $test, string $raw, array $terms, array $bigrams): int
    {
        $score = 0;

        // 名称类字段（权重：短名 1.0 / 展示名 0.9 / 原始标题 0.6）
        foreach (['short_name' => 10, 'display_name' => 9, 'title' => 6] as $field => $weight) {
            $value = mb_strtolower(trim((string)$test->$field));
            if ($value === '') continue;

            if ($value === $raw) $score += 10 * $weight;
            elseif ($this->startsWith($value, $raw)) $score += 6 * $weight;
            elseif ($this->contains($value, $raw)) $score += 4 * $weight;

            foreach ($terms as $term) {
                if ($term === $raw) continue;
                if ($value === $term) $score += 3 * $weight;
                elseif ($this->startsWith($value, $term)) $score += 2 * $weight;
                elseif ($this->contains($value, $term)) $score += $weight;
            }
            // 中文二元字组兜底命中（权重低，且限制总贡献）
            $bgHit = 0;
            foreach ($bigrams as $bg) {
                if ($this->contains($value, $bg)) $bgHit++;
            }
            $score += min($bgHit, 3) * (int)round($weight / 2);
        }

        // 标签命中（注意：不允许跨词段边界包含，避免 "mbti人格" 误命中标签 "i人"）
        $tagScore = 0;
        foreach (($test->tags ?: []) as $tag) {
            $value = mb_strtolower(trim((string)$tag));
            if ($value === '') continue;
            if ($value === $raw) {
                $tagScore += 36;
            } elseif ($this->contains($value, $raw)) {
                // 标签包含完整输入，如 输入"人格" 命中标签 "人格测试"
                $tagScore += 24;
            } else {
                foreach ($terms as $term) {
                    if ($value === $term) {
                        // term===raw 时已在上方按完整命中计 36 分
                        if ($term !== $raw) $tagScore += 24;
                    } elseif ($this->contains($term, $value)) {
                        // 输入词段包含标签；词段不跨拉丁/中文边界，无跨界误命中
                        $tagScore += 16;
                    } elseif ($term !== $raw && $this->contains($value, $term)) {
                        // term===raw 时与上方「标签包含完整输入」重复，跳过
                        $tagScore += 8;
                    }
                }
            }
        }
        $score += min($tagScore, 40);

        // 分类中文名命中
        $categoryName = mb_strtolower($this->categoryName((string)$test->category));
        if ($categoryName !== '') {
            if ($categoryName === $raw) $score += 20;
            elseif ($this->contains($categoryName, $raw)) $score += 12;
            else {
                foreach ($terms as $term) {
                    if ($term !== $raw && $this->contains($categoryName, $term)) $score += 6;
                }
            }
        }

        // 描述命中仅作小幅加权（需名称/标签/分类已有命中，避免噪声）
        if ($score > 0) {
            $desc = mb_strtolower(trim((string)$test->description));
            if ($desc !== '') {
                if ($this->contains($desc, $raw)) $score += 6;
                $termHits = 0;
                foreach ($terms as $term) {
                    if ($term !== $raw && $this->contains($desc, $term)) $termHits++;
                }
                $score += min($termHits, 3) * 2;
            }
        }

        return $score;
    }

    protected function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }

    protected function startsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
    }

    /**
     * 分类页
     */
    public function category(string $category): array
    {
        $meta = null;
        foreach (self::CATEGORIES as $c) {
            if ($c['key'] === $category) $meta = $c;
        }
        if (!$meta) return ['meta' => null, 'tests' => []];

        $tests = array_map(fn($t) => $this->card($t), (new Test())->where('site_id', $this->site_id)
            ->where('online_status', 1)->where('category', $category)
            ->order('sort desc, test_id asc')->select()->all());

        return ['meta' => $meta, 'tests' => $tests];
    }

    /**
     * 介绍页详情（不含题目）
     */
    public function intro(int $test_id): array
    {
        $test = $this->onlineTest($test_id);
        return [
            'test_id'        => $test->test_id,
            'display_name'   => $test->display_name,
            'short_name'     => $test->short_name,
            'category'       => $test->category,
            'category_name'  => $this->categoryName($test->category),
            'theme_key'      => $test->theme_key,
            'scoring_model'  => $test->scoring_model,
            'cover'          => $test->cover,
            'tags'           => $test->tags ?: [],
            'question_count' => $test->question_count,
            'result_count'   => $test->result_count,
            'est_minutes'    => $test->est_minutes,
            'description'    => $test->description,
            'instructions'   => $test->instructions,
            'compliance'     => $test->compliance ?: [],
            'copyright_status' => $test->copyright_status,
            'is_partner'     => $test_id === 7,
            'joined_count'   => $this->joinCount($test_id),
            'config'         => (new CepingConfigService())->getPublicConfig($this->site_id),
        ];
    }

    /**
     * 作答试卷（剥离score）
     */
    public function paper(int $test_id): array
    {
        $test = $this->onlineTest($test_id);
        $questions = [];
        foreach (($test->questions ?: []) as $q) {
            $questions[] = [
                'no'      => (int)$q['no'],
                'stem'    => $q['stem'],
                'options' => array_map(fn($o) => ['key' => $o['key'], 'text' => $o['text']], $q['options']),
            ];
        }
        return [
            'test_id'        => $test_id,
            'display_name'   => $test->display_name,
            'theme_key'      => $test->theme_key,
            'scoring_model'  => $test->scoring_model,
            'question_count' => $test->question_count,
            'questions'      => $questions,
        ];
    }

    public function findOnlineModel(int $test_id): Test
    {
        return $this->onlineTest($test_id);
    }

    protected function onlineTest(int $test_id): Test
    {
        $test = (new Test())->where('site_id', $this->site_id)
            ->where('test_id', $test_id)->where('online_status', 1)->findOrEmpty();
        if ($test->isEmpty()) throw new ApiException('CEPING_TEST_NOT_FOUND');
        return $test;
    }

    protected function card(Test $test): array
    {
        return [
            'test_id'        => $test->test_id,
            'display_name'   => $test->display_name,
            'short_name'     => $test->short_name,
            'category'       => $test->category,
            'category_name'  => $this->categoryName($test->category),
            'theme_key'      => $test->theme_key,
            'cover'          => $test->cover,
            'tags'           => array_slice($test->tags ?: [], 0, 3),
            'question_count' => $test->question_count,
            'est_minutes'    => $test->est_minutes,
            'is_hot'         => (int)$test->is_hot,
            'is_partner'     => (int)$test->test_id === 7,
            'joined_count'   => $this->joinCount((int)$test->test_id),
        ];
    }

    protected function categoryName(string $key): string
    {
        foreach (self::CATEGORIES as $c) if ($c['key'] === $key) return $c['name'];
        return '';
    }

    /**
     * 参与人数（5分钟缓存，排除待配对/取消）
     */
    protected function joinCount(int $test_id): int
    {
        $key = "ceping:join_count:{$this->site_id}:{$test_id}";
        return (int)Cache::remember($key, function () use ($test_id) {
            return (new Record())->where('site_id', $this->site_id)
                ->where('test_id', $test_id)
                ->whereIn('status', ['completed', 'paired'])
                ->count();
        }, 300);
    }
}
