<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Cli;

use Bss\UpgradePredictor\Analyzer\ClassDiffAnalyzer;
use Bss\UpgradePredictor\Analyzer\PluginAnalyzer;
use Bss\UpgradePredictor\Analyzer\TemplateAnalyzer;
use Bss\UpgradePredictor\Analyzer\LayoutAnalyzer;
use Bss\UpgradePredictor\Scanner\DiXmlScanner;
use Bss\UpgradePredictor\Scanner\TemplateScanner;
use Bss\UpgradePredictor\Scanner\LayoutScanner;
use Bss\UpgradePredictor\Reporter\MarkdownReporter;
use Bss\UpgradePredictor\Reporter\JsonReporter;
use Bss\UpgradePredictor\Reporter\GithubAnnotationReporter;
use Bss\UpgradePredictor\Reporter\ReporterInterface;
use Bss\UpgradePredictor\Config\Config;
use Bss\UpgradePredictor\Snapshot\SnapshotStorage;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\Severity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('analyze')
            ->setDescription('Analyze current codebase against a snapshot for upgrade compatibility issues')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Target version snapshot to use (default: latest)')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of analyzers to run (class-diff,plugin,template,layout)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: markdown, json, github (default: from config)')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: stdout)')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Exit with error on: critical, warning, info (default: from config)')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = getcwd();
        $config = Config::load($projectDir, $input->getOption('config'));

        // Load snapshot
        $storage = new SnapshotStorage($projectDir . '/' . $config->snapshotDir);

        try {
            if ($input->getOption('target')) {
                $snapshot = $storage->load($input->getOption('target'));
            } else {
                $snapshot = $storage->loadLatest();
            }
        } catch (\RuntimeException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            $output->writeln('<error>Run "snapshot <target-version>" first to create a snapshot.</error>');
            return Command::FAILURE;
        }

        // Build scan paths (resolve relative to projectDir)
        $scanPaths = [];
        foreach ($config->scanPaths as $path) {
            $scanPaths[] = $projectDir . '/' . ltrim($path, '/');
        }
        foreach ($config->extraVendors as $path) {
            $scanPaths[] = $projectDir . '/' . ltrim($path, '/');
        }

        // Determine which analyzers to run
        $onlyOption = $input->getOption('only');
        $enabledAnalyzers = $onlyOption
            ? array_map('trim', explode(',', $onlyOption))
            : ['class-diff', 'plugin', 'template', 'layout'];

        $allIssues = new IssueCollection();

        // Run DiXmlScanner → ClassDiffAnalyzer and/or PluginAnalyzer
        $needsDi = in_array('class-diff', $enabledAnalyzers, true) || in_array('plugin', $enabledAnalyzers, true);
        if ($needsDi) {
            $diScanner = new DiXmlScanner();
            $diResult = $diScanner->scan($scanPaths);

            if (in_array('class-diff', $enabledAnalyzers, true)) {
                $classDiffAnalyzer = new ClassDiffAnalyzer();
                $allIssues = $allIssues->merge($classDiffAnalyzer->analyze($snapshot, $diResult));
            }

            if (in_array('plugin', $enabledAnalyzers, true)) {
                $pluginAnalyzer = new PluginAnalyzer();
                $allIssues = $allIssues->merge($pluginAnalyzer->analyze($snapshot, $diResult));
            }
        }

        // Run TemplateScanner → TemplateAnalyzer (only if vendor-path.txt exists)
        if (in_array('template', $enabledAnalyzers, true)) {
            $snapshotDir = $projectDir . '/' . $config->snapshotDir . '/' . $snapshot->toVersion;
            $vendorPathFile = $snapshotDir . '/vendor-path.txt';

            if (file_exists($vendorPathFile)) {
                $targetVendorPath = trim(file_get_contents($vendorPathFile));
                $templateScanner = new TemplateScanner($projectDir);
                $templateResult = $templateScanner->scan();
                $templateAnalyzer = new TemplateAnalyzer($projectDir . '/vendor', $targetVendorPath);
                $allIssues = $allIssues->merge($templateAnalyzer->analyze($snapshot, $templateResult));
            } else {
                $output->writeln('<comment>Skipping template analysis: no vendor-path.txt found. Use --keep-source when snapshotting.</comment>');
            }
        }

        // Run LayoutScanner → LayoutAnalyzer
        if (in_array('layout', $enabledAnalyzers, true)) {
            $layoutScanner = new LayoutScanner();
            $layoutResult = $layoutScanner->scan($scanPaths);
            $layoutAnalyzer = new LayoutAnalyzer($projectDir . '/vendor');
            $allIssues = $allIssues->merge($layoutAnalyzer->analyze($snapshot, $layoutResult));
        }

        // Apply ignore patterns
        $ignorePatterns = $config->ignore;
        if (!empty($ignorePatterns)) {
            $filtered = [];
            foreach ($allIssues->all() as $issue) {
                $ignored = false;
                foreach ($ignorePatterns as $pattern) {
                    // Namespace patterns (contains \) match targetClass via fnmatch
                    if (str_contains($pattern, '\\')) {
                        if (fnmatch($pattern, $issue->targetClass)) {
                            $ignored = true;
                            break;
                        }
                    } else {
                        // File patterns match sourceFile via fnmatch
                        if (fnmatch($pattern, $issue->sourceFile)) {
                            $ignored = true;
                            break;
                        }
                    }
                }
                if (!$ignored) {
                    $filtered[] = $issue;
                }
            }
            $allIssues = new IssueCollection($filtered);
        }

        // Select reporter
        $format = $input->getOption('format') ?? $config->output;
        $reporter = $this->createReporter($format);

        // Render output
        $reportContent = $reporter->render($allIssues, $snapshot->fromVersion, $snapshot->toVersion);

        // Write to file or stdout
        $outFile = $input->getOption('out');
        if ($outFile) {
            file_put_contents($outFile, $reportContent);
            $output->writeln("<info>Report written to {$outFile}</info>");
        } else {
            $output->write($reportContent);
        }

        // Determine exit code
        $failOn = $input->getOption('fail-on') ?? $config->failOn;
        return $this->resolveExitCode($allIssues, $failOn);
    }

    private function createReporter(string $format): ReporterInterface
    {
        return match ($format) {
            'json'   => new JsonReporter(),
            'github' => new GithubAnnotationReporter(),
            default  => new MarkdownReporter(),
        };
    }

    private function resolveExitCode(IssueCollection $issues, string $failOn): int
    {
        $summary = $issues->summary();

        if ($summary['critical'] > 0) {
            // Always return 1 for critical issues (regardless of fail_on)
            return 1;
        }

        if ($failOn === 'warning' || $failOn === 'info') {
            if ($summary['warning'] > 0 || ($failOn === 'info' && $summary['info'] > 0)) {
                return 2;
            }
        }

        return Command::SUCCESS;
    }
}
