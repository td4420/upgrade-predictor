<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Reporter;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\Severity;

class MarkdownReporter implements ReporterInterface
{
    public function render(IssueCollection $issues, string $fromVersion, string $toVersion): string
    {
        $lines = [];
        $lines[] = "# Upgrade Impact Report: {$fromVersion} → {$toVersion}";
        $lines[] = '';

        $summary = $issues->summary();
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Severity | Count |';
        $lines[] = '|----------|-------|';
        $lines[] = "| CRITICAL | {$summary['critical']} |";
        $lines[] = "| WARNING  | {$summary['warning']} |";
        $lines[] = "| INFO     | {$summary['info']} |";
        $lines[] = '';

        if (count($issues) === 0) {
            $lines[] = 'No issues found. Upgrade looks safe!';
            return implode("\n", $lines);
        }

        $lines[] = '## Issues';
        $lines[] = '';

        $severityOrder = [Severity::CRITICAL, Severity::WARNING, Severity::INFO];

        foreach ($severityOrder as $severity) {
            $group = $issues->filterBySeverity($severity);
            if (count($group) === 0) {
                continue;
            }

            $lines[] = '### ' . strtoupper($severity->value);
            $lines[] = '';

            foreach ($group as $issue) {
                /** @var Issue $issue */
                $lines[] = "#### [{$issue->analyzer}] {$issue->targetClass}";
                $lines[] = '';
                $lines[] = "- **File:** `{$issue->sourceFile}`" . ($issue->sourceLine !== null ? " (line {$issue->sourceLine})" : '');
                $lines[] = "- **Message:** {$issue->message}";

                if ($issue->suggestion !== null) {
                    $lines[] = "- **Suggestion:** {$issue->suggestion}";
                }

                if ($issue->diff !== null) {
                    $lines[] = '';
                    $lines[] = '```diff';
                    $lines[] = $issue->diff;
                    $lines[] = '```';
                }

                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
