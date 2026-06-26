<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Reporter;

use Bss\UpgradePredictor\Model\IssueCollection;

interface ReporterInterface
{
    public function render(IssueCollection $issues, string $fromVersion, string $toVersion): string;
}
