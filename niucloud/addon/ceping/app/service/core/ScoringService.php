<?php

namespace addon\ceping\app\service\core;

use core\base\BaseCoreService;
use core\exception\ApiException;

/**
 * 计分引擎（category / threshold / dimension / partner）
 * 服务端独立复核，以本服务输出结果为准
 */
class ScoringService extends BaseCoreService
{
    /**
     * 校验作答完整性与合法性，返回 [题目map, 答案map(no=>optKey)]
     */
    public function validateAnswers(array $test, array $answers): array
    {
        $questions = $test['questions'] ?? [];
        if (empty($questions)) throw new ApiException('CEPING_PAPER_EMPTY');

        $question_map = [];
        foreach ($questions as $q) {
            $question_map[(int)$q['no']] = $q;
        }
        $answer_map = [];
        foreach ($answers as $row) {
            $no = (int)($row['no'] ?? 0);
            $opt_key = (string)($row['optKey'] ?? '');
            if ($no <= 0 || !isset($question_map[$no])) throw new ApiException('CEPING_ANSWER_INVALID');
            if (!in_array($opt_key, ['A', 'B', 'C', 'D'])) throw new ApiException('CEPING_ANSWER_INVALID');
            $valid_keys = array_column($question_map[$no]['options'], 'key');
            if (!in_array($opt_key, $valid_keys)) throw new ApiException('CEPING_ANSWER_INVALID');
            $answer_map[$no] = $opt_key;
        }
        if (count($answer_map) !== count($questions)) throw new ApiException('CEPING_ANSWER_INCOMPLETE');
        foreach ($question_map as $no => $q) {
            if (!isset($answer_map[$no])) throw new ApiException('CEPING_ANSWER_INCOMPLETE');
        }
        return [$question_map, $answer_map];
    }

    /**
     * 单测评计分（服务端复核）
     * @return array{result_key:string,result_name:string,total_score:int,score_detail:array}
     */
    public function score(array $test, array $answers): array
    {
        [, $answer_map] = $this->validateAnswers($test, $answers);

        switch ($test['scoringModel'] ?? $test['scoring_model'] ?? '') {
            case 'category':
                return $this->scoreCategory($test, $answer_map);
            case 'threshold':
                return $this->scoreThreshold($test, $answer_map);
            case 'dimension':
                return $this->scoreDimension($test, $answer_map);
            default:
                throw new ApiException('CEPING_SCORING_MODEL_UNSUPPORTED');
        }
    }

    /**
     * 取每题所选选项
     */
    protected function chosenOptions(array $test, array $answer_map): array
    {
        $out = [];
        foreach ($test['questions'] as $q) {
            $no = (int)$q['no'];
            foreach ($q['options'] as $opt) {
                if ($opt['key'] === $answer_map[$no]) {
                    $out[$no] = $opt;
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * category 分类累加：平票按 results 数组顺序取靠前
     */
    protected function scoreCategory(array $test, array $answer_map): array
    {
        $sum = [];
        foreach (($test['categories'] ?? []) as $c) {
            $sum[$c['key']] = 0;
        }
        foreach ($this->chosenOptions($test, $answer_map) as $opt) {
            $cat = $opt['score']['cat'] ?? '';
            $points = (int)($opt['score']['points'] ?? 1);
            if ($cat !== '') $sum[$cat] = ($sum[$cat] ?? 0) + $points;
        }
        arsort($sum);
        $top_score = max($sum ?: [0]);
        // 平票：按 results 数组顺序取靠前者
        $result_key = '';
        foreach (($test['results'] ?? []) as $r) {
            if (($sum[$r['categoryKey'] ?? $r['key']] ?? -1) === $top_score) {
                $result_key = $r['key'];
                break;
            }
        }
        // 第二名与分差
        arsort($sum);
        $vals = array_values($sum);
        $second_score = $vals[1] ?? null;
        return $this->packResult($test, $result_key, (int)array_sum($sum), [
            'model'      => 'category',
            'categories' => $sum,
            'top_score'  => $top_score,
            'second_score' => $second_score,
            'gap'        => $second_score === null ? null : $top_score - $second_score,
        ]);
    }

    /**
     * threshold 分数区间：边界取高一级
     */
    protected function scoreThreshold(array $test, array $answer_map): array
    {
        $total = 0;
        foreach ($this->chosenOptions($test, $answer_map) as $opt) {
            $total += (int)($opt['score']['points'] ?? 0);
        }
        $bands = $test['bands'] ?? [];
        $result_key = '';
        $hit_band = null;
        foreach ($bands as $i => $band) {
            if ($total >= (int)$band['min'] && $total <= (int)$band['max']) {
                // 恰好落在上界且下一档最小值=当前值，取高一级
                if (isset($bands[$i + 1]) && $total === (int)$band['max'] && (int)$bands[$i + 1]['min'] <= $total) {
                    continue;
                }
                $result_key = $band['key'];
                $hit_band = $band;
                break;
            }
        }
        if ($result_key === '' && !empty($bands)) {
            // 兜底：低于最低档/高于最高档
            $hit_band = $total < (int)$bands[0]['min'] ? $bands[0] : $bands[count($bands) - 1];
            $result_key = $hit_band['key'];
        }
        return $this->packResult($test, $result_key, $total, [
            'model' => 'threshold',
            'total' => $total,
            'band'  => $hit_band,
        ]);
    }

    /**
     * dimension 多维轴：平票取 poles[1]，match 支持 * 通配
     */
    protected function scoreDimension(array $test, array $answer_map): array
    {
        $axes = $test['axes'] ?? [];
        $match = [];
        $counts = [];
        foreach ($axes as $axis) {
            $counter = [];
            foreach ($axis['poles'] as $pole) $counter[$pole['key']] = 0;
            foreach ($this->chosenOptions($test, $answer_map) as $opt) {
                $sc = $opt['score'] ?? [];
                if (($sc['axis'] ?? '') === $axis['key'] && isset($counter[$sc['pole']])) {
                    $counter[$sc['pole']]++;
                }
            }
            arsort($counter);
            $top = max($counter ?: [0]);
            $pole_key = '';
            // 平票取第二个 pole
            $tie = array_filter($counter, fn($v) => $v === $top);
            if (count($tie) > 1) {
                $pole_key = $axis['poles'][1]['key'];
            } else {
                $pole_key = array_key_first($tie);
            }
            $match[$axis['key']] = $pole_key;
            $counts[$axis['key']] = $counter;
        }
        $result_key = '';
        foreach (($test['results'] ?? []) as $r) {
            $rm = $r['match'] ?? [];
            $ok = true;
            foreach ($match as $axis_key => $pole) {
                $want = $rm[$axis_key] ?? '*';
                if ($want !== '*' && $want !== $pole) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $result_key = $r['key'];
                break;
            }
        }
        return $this->packResult($test, $result_key, 0, [
            'model'  => 'dimension',
            'axes'   => $match,
            'counts' => $counts,
        ]);
    }

    protected function packResult(array $test, string $result_key, int $total, array $detail): array
    {
        $result_name = '';
        foreach (($test['results'] ?? []) as $r) {
            if ($r['key'] === $result_key) {
                $result_name = $r['name'];
                break;
            }
        }
        return [
            'result_key'   => $result_key,
            'result_name'  => $result_name,
            'total_score'  => $total,
            'score_detail' => $detail,
        ];
    }

    /**
     * #7 双人契合度计算
     * @param array $test 题库
     * @param array $inviter_answers A作答
     * @param array $invitee_answers B作答
     * @return array {pairScore,sync,warmth,sa,sb,sameCount,diffQuestions,score_detail}
     */
    public function scorePartner(array $test, array $inviter_answers, array $invitee_answers): array
    {
        [, $a_map] = $this->validateAnswers($test, $inviter_answers);
        [, $b_map] = $this->validateAnswers($test, $invitee_answers);

        $points = function (array $answer_map) use ($test) {
            $pts = [];
            foreach ($test['questions'] as $q) {
                $no = (int)$q['no'];
                foreach ($q['options'] as $opt) {
                    if ($opt['key'] === $answer_map[$no]) {
                        $pts[$no] = (int)($opt['score']['points'] ?? 0);
                        break;
                    }
                }
            }
            return $pts;
        };
        $a = $points($a_map);
        $b = $points($b_map);
        ksort($a);
        ksort($b);

        $sa = array_sum($a);
        $sb = array_sum($b);
        $sync = 0;
        $same_count = 0;
        $diff_map = [];
        foreach ($a as $no => $av) {
            $bv = $b[$no];
            $sync += max(0, 3 - abs($av - $bv));
            if ($av === $bv) $same_count++;
            $diff_map[$no] = abs($av - $bv);
        }
        arsort($diff_map);
        $diff_nos = array_slice(array_keys(array_filter($diff_map, fn($v) => $v > 0)), 0, 2);
        $diff_questions = [];
        foreach ($test['questions'] as $q) {
            if (in_array((int)$q['no'], $diff_nos, true)) $diff_questions[] = $q['stem'];
        }

        $warmth = ($sa + $sb) / 2;
        $pair_score = (int)round($sync / 30 * 60 + $warmth / 30 * 40);

        // 档位以 warmth 命中 bands（边界取高一级，同 threshold 规则）
        $band_key = '';
        $bands = $test['bands'] ?? [];
        foreach ($bands as $i => $band) {
            if ($warmth >= (int)$band['min'] && $warmth <= (int)$band['max']) {
                if (isset($bands[$i + 1]) && $warmth == (int)$band['max'] && (int)$bands[$i + 1]['min'] <= $warmth) continue;
                $band_key = $band['key'];
                break;
            }
        }
        if ($band_key === '' && !empty($bands)) {
            $band_key = $warmth < (int)$bands[0]['min'] ? $bands[0]['key'] : $bands[count($bands) - 1]['key'];
        }
        $result_name = '';
        foreach (($test['results'] ?? []) as $r) {
            if ($r['key'] === $band_key) {
                $result_name = $r['name'];
                break;
            }
        }

        return [
            'result_key'  => $band_key,
            'result_name' => $result_name,
            'total_score' => $pair_score,
            'score_detail' => [
                'model'         => 'partner',
                'pairScore'     => $pair_score,
                'sync'          => $sync,
                'warmth'        => $warmth,
                'sa'            => $sa,
                'sb'            => $sb,
                'sameCount'     => $same_count,
                'totalCount'    => count($a),
                'diffQuestions' => $diff_questions,
            ],
        ];
    }
}
