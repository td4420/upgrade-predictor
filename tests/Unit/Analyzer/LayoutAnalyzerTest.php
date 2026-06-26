<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Analyzer;

use Bss\UpgradePredictor\Analyzer\LayoutAnalyzer;
use Bss\UpgradePredictor\Model\ClassMap;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;
use PHPUnit\Framework\TestCase;

class LayoutAnalyzerTest extends TestCase
{
    private string $fixtureDir;
    protected function setUp(): void { $this->fixtureDir = __DIR__ . '/../../Fixtures/layout-registry'; }

    public function testReferenceToExistingBlockIsOk(): void
    {
        $snap = new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 1);
        $scan = new ScanResult('layout', [['type' => 'reference', 'data' => ['referenceType' => 'block', 'referenceName' => 'product.info.main', 'layoutFile' => 'x.xml', 'layoutLine' => 5]]]);
        $this->assertCount(0, (new LayoutAnalyzer($this->fixtureDir . '/vendor'))->analyze($snap, $scan));
    }

    public function testReferenceToMissingBlockIsWarning(): void
    {
        $snap = new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 1);
        $scan = new ScanResult('layout', [['type' => 'reference', 'data' => ['referenceType' => 'block', 'referenceName' => 'nonexistent.block', 'layoutFile' => 'x.xml', 'layoutLine' => 5]]]);
        $issues = (new LayoutAnalyzer($this->fixtureDir . '/vendor'))->analyze($snap, $scan);
        $this->assertCount(1, $issues);
        $this->assertSame(Severity::WARNING, $issues->all()[0]->severity);
    }

    public function testReferenceToExistingContainerIsOk(): void
    {
        $snap = new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 1);
        $scan = new ScanResult('layout', [['type' => 'reference', 'data' => ['referenceType' => 'container', 'referenceName' => 'content', 'layoutFile' => 'x.xml', 'layoutLine' => 5]]]);
        $this->assertCount(0, (new LayoutAnalyzer($this->fixtureDir . '/vendor'))->analyze($snap, $scan));
    }

    public function testMoveWithMissingDestinationIsWarning(): void
    {
        $snap = new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 1);
        $scan = new ScanResult('layout', [['type' => 'move', 'data' => ['element' => 'product.info.main', 'destination' => 'nonexistent.container', 'layoutFile' => 'x.xml', 'layoutLine' => 10]]]);
        $issues = (new LayoutAnalyzer($this->fixtureDir . '/vendor'))->analyze($snap, $scan);
        $this->assertGreaterThanOrEqual(1, count($issues));
        $this->assertSame(Severity::WARNING, $issues->all()[0]->severity);
    }
}
