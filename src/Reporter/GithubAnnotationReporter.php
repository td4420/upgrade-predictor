<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Reporter;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\Severity;

class GithubAnnotationReporter implements ReporterInterface
{
    public function render(IssueCollection $issues, string $fromVersion, string $toVersion): string
    {
        $lines = [];

        foreach ($issues as $issue) {
            /** @var Issue $issue */
            $level = match ($issue->severity) {
                Severity::CRITICAL => 'error',
                Severity::WARNING  => 'warning',
                Severity::INFO     => 'notice',
            };

            $location = "file={$issue->sourceFile}";
            if ($issue->sourceLine !== null) {
                $location .= ",line={$issue->sourceLine}";
            }

            $lines[] = "::{$level} {$location}::[{$issue->analyzer}] {$issue->message}";
        }

        return implode("\n", $lines);
    }
}
