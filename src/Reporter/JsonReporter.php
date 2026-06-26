<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Reporter;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;

class JsonReporter implements ReporterInterface
{
    public function render(IssueCollection $issues, string $fromVersion, string $toVersion): string
    {
        $issuesArray = [];
        foreach ($issues as $issue) {
            /** @var Issue $issue */
            $issuesArray[] = [
                'severity'   => $issue->severity->value,
                'analyzer'   => $issue->analyzer,
                'sourceFile' => $issue->sourceFile,
                'sourceLine' => $issue->sourceLine,
                'targetClass' => $issue->targetClass,
                'message'    => $issue->message,
                'diff'       => $issue->diff,
                'suggestion' => $issue->suggestion,
            ];
        }

        $data = [
            'from'         => $fromVersion,
            'to'           => $toVersion,
            'generated_at' => date('c'),
            'summary'      => $issues->summary(),
            'issues'       => $issuesArray,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
