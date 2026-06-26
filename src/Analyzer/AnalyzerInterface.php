<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Analyzer;

use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Snapshot;

interface AnalyzerInterface
{
    public function getName(): string;
    public function analyze(Snapshot $snapshot, ScanResult $scanResult): IssueCollection;
}
