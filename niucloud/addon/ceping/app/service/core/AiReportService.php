<?php

namespace addon\ceping\app\service\core;

use addon\ceping\app\model\AiReport;
use core\base\BaseCoreService;

/**
 * AI深度报告生成（四道防线 + 本人/结果缓存）
 */
class AiReportService extends BaseCoreService
{
    const CRISIS_WORDS = ['自杀', '不想活', '活不下去', '伤害自己', '结束生命', '轻生', '自残', '了断'];

    /**
     * @param array $cfg ceping_config
     * @param array $ctx {site_id,member_id,record_id,test_id,result_key,result,answers,score_detail,display_name}
     */
    public function generate(array $cfg, array $ctx, AiReport $report): void
    {
        try {
            // 防线4：危机词兜底（在调用前）
            if ($this->hitCrisis($ctx)) {
                $report->save([
                    'status' => 'ready',
                    'is_safety' => 1,
                    'content' => $this->safetyContent(),
                ]);
                return;
            }

            // 防线1：本人同题同答案缓存
            $hash = $this->answersHash($ctx['answers']);
            $cached = (new AiReport())->where('member_id', $ctx['member_id'])
                ->where('test_id', $ctx['test_id'])
                ->where('answers_hash', $hash)
                ->where('status', 'ready')
                ->where('id', '<>', $report->id)
                ->findOrEmpty();

            // 防线2：同结果全局文案缓存
            if ($cached->isEmpty()) {
                $cached = (new AiReport())->where('site_id', $ctx['site_id'])
                    ->where('test_id', $ctx['test_id'])
                    ->where('result_key', $ctx['result_key'])
                    ->where('status', 'ready')->where('is_safety', 0)
                    ->where('id', '<>', $report->id)
                    ->order('id desc')->findOrEmpty();
            }

            if (!$cached->isEmpty()) {
                $report->save([
                    'status' => 'ready',
                    'is_cached' => 1,
                    'content' => $cached->content,
                    'model' => $cached->model,
                    'answers_hash' => $hash,
                ]);
                return;
            }

            if (empty($cfg['ai_enabled']) || empty($cfg['ai_base_url']) || empty($cfg['ai_api_key']) || empty($cfg['ai_model'])) {
                $report->save(['status' => 'failed', 'fail_reason' => 'AI_NOT_CONFIGURED']);
                return;
            }

            // 防线3：调用LLM
            [$system, $user] = $this->buildPrompt($ctx);
            $raw = (new LlmService())->chat($cfg, $system, $user, true);
            $content = json_decode($raw, true);
            if (!is_array($content) || empty($content)) {
                // 容错：包成纯文本
                $content = ['title' => $ctx['result']['name'] ?? '深度报告', 'sections' => [['heading' => '解读', 'text' => strip_tags($raw)]], 'advices' => []];
            }
            $content['nonDiagnostic'] = '本内容由AI生成，仅供娱乐参考，不构成心理/医疗诊断或治疗建议。';

            $report->save([
                'status' => 'ready',
                'content' => $content,
                'model' => $cfg['ai_model'],
                'prompt_ver' => $cfg['prompt_ver'] ?: 'v1',
                'answers_hash' => $hash,
                'fail_reason' => '',
            ]);
        } catch (\Throwable $e) {
            $report->save(['status' => 'failed', 'fail_reason' => mb_substr($e->getMessage(), 0, 200)]);
        }
    }

    public function answersHash(array $answers): string
    {
        $flat = [];
        foreach ($answers as $a) $flat[] = ($a['no'] ?? '') . ':' . ($a['optKey'] ?? '');
        sort($flat);
        return hash('sha256', implode('|', $flat));
    }

    protected function hitCrisis(array $ctx): bool
    {
        $blob = json_encode($ctx['answers'] ?? [], JSON_UNESCAPED_UNICODE)
            . ($ctx['result']['detail'] ?? '') . ($ctx['result']['summary'] ?? '');
        foreach (self::CRISIS_WORDS as $w) {
            if (mb_strpos($blob, $w) !== false) return true;
        }
        return false;
    }

    protected function safetyContent(): array
    {
        return [
            'title' => '想先给你一个拥抱',
            'sections' => [[
                'heading' => '重要的话',
                'text' => '我们注意到你此刻可能正经历强烈的痛苦。你不必独自承受，寻求帮助是勇敢的选择。',
            ]],
            'advices' => [
                '全国心理援助热线：400-161-9995（24小时）',
                '北京心理危机研究与干预中心：010-82951332',
                '如你或身边的人有即刻危险，请立即拨打120或前往最近的医院急诊。',
            ],
            'isSafety' => true,
        ];
    }

    protected function buildPrompt(array $ctx): array
    {
        $r = $ctx['result'];
        $system = '你是一位温暖、专业的心理学科普作者。输出必须是JSON对象，结构：'
            . '{"title":"报告标题","sections":[{"heading":"小节标题","text":"300字内"}],"advices":["3条可执行建议"]}。'
            . '禁止医疗诊断用语（如确诊、疾病、治疗、患者），语言具体有洞察，避免空泛贴标签。';
        $answer_lines = [];
        foreach (($ctx['answers'] ?? []) as $a) {
            $answer_lines[] = '第' . $a['no'] . '题选' . $a['optKey'];
        }
        $user = "测评：{$ctx['display_name']}\n"
            . "用户结果类型：{$r['name']}（{$ctx['result_key']}）\n"
            . "结果概述：" . ($r['summary'] ?? '') . "\n"
            . "作答选择：" . implode('，', $answer_lines) . "\n"
            . "计分明细：" . json_encode($ctx['score_detail'] ?? [], JSON_UNESCAPED_UNICODE) . "\n"
            . "请基于以上信息写一份约800字的个性化深度解读报告（3个小节），并给出3条本周可执行的小建议。";
        return [$system, $user];
    }
}
