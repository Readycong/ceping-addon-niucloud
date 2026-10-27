<?php

namespace addon\ceping\app\command;

use addon\ceping\app\service\core\ImportService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 爱测评题库导入命令
 * php think ceping:import-tests [--dry-run] [--site=1000]
 */
class ImportTests extends Command
{
    protected function configure()
    {
        $this->setName('ceping:import-tests')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只核对差异，不写库')
            ->addOption('site', null, Option::VALUE_OPTIONAL, '站点id', 1000)
            ->addOption('force', null, Option::VALUE_NONE, '有warn时仍继续（默认error中止）')
            ->setDescription('导入爱测评50份题库（可重复执行，保护运营字段）');
    }

    protected function execute(Input $input, Output $output)
    {
        $site_id = (int)$input->getOption('site');
        $service = new ImportService();

        if ($input->getOption('dry-run')) {
            $report = $service->dryRun($site_id);
            $output->writeln('题库目录: ' . $report['bank_dir']);
            $output->writeln("题库总数: {$report['total']}  新增: {$report['new_count']}  更新: {$report['update_count']}");
            $output->writeln('------------------------------------------------------------');
            foreach ($report['rows'] as $row) {
                $tag = $row['is_new'] ? '[新增]' : '[更新]';
                $hot = $row['is_hot'] ? '★' : ' ';
                $output->writeln(sprintf(
                    ' %s %s #%02d %-28s 类:%-9s 模型:%-9s 题:%2d 果:%2d 版权:%s',
                    $tag, $hot, $row['test_id'], $row['display_name'], $row['category'],
                    $row['scoring_model'], $row['question_count'], $row['result_count'], $row['copyright']
                ));
            }
            $output->writeln('------------------------------------------------------------');
            $errors = array_filter($report['issues'], fn($i) => $i['level'] === 'error');
            $warns = array_filter($report['issues'], fn($i) => $i['level'] === 'warn');
            foreach ($report['issues'] as $issue) {
                $prefix = $issue['level'] === 'error' ? '<error>[ERROR]</error>' : '<comment>[WARN] </comment>';
                $output->writeln($prefix . ' ' . $issue['msg']);
            }
            $output->writeln('问题统计: error=' . count($errors) . ' warn=' . count($warns));
            return 0;
        }

        try {
            $result = $service->import($site_id, true);
            $output->writeln("<info>导入完成: 新增{$result['insert']} 更新{$result['update']} 共{$result['total']}</info>");
            $warns = array_filter($result['issues'], fn($i) => $i['level'] === 'warn');
            foreach ($warns as $w) $output->writeln('<comment>[WARN] ' . $w['msg'] . '</comment>');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>[FATAL] ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
