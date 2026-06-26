<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Analyzer;

use Bss\UpgradePredictor\Analyzer\TemplateAnalyzer;
use Bss\UpgradePredictor\Model\ClassMap;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;
use PHPUnit\Framework\TestCase;

class TemplateAnalyzerTest extends TestCase
{
    private string $fixtureDir;
    protected function setUp(): void { $this->fixtureDir = __DIR__ . '/../../Fixtures/template-diff'; }

    public function testDetectsChangedTemplate(): void
    {
        $snapshot = new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 1);
        $scan = new ScanResult('template', [['type' => 'template', 'data' => ['overridePath' => '/some/theme/Magento_Catalog/templates/product/view.phtml', 'coreModuleName' => 'Magento_Catalog', 'coreTemplatePath' => 'product/view.phtml']]]);
        $analyzer = new TemplateAnalyzer($this->fixtureDir . '/old-vendor', $this->fixtureDir . '/new-vendor');
        $issues = $analyzer->analyze($snapshot, $scan);
        $this->assertCount(1, $issues);
        $this->assertSame(Severity::WARNING, $issues->all()[0]->severity);
        $this->assertNotNull($issues->all()[0]->diff);
    }

    public function testNoChangeProducesNoIssues(): void
    {
        $snapshot = new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 1);
        $scan = new ScanResult('template', [['type' => 'template', 'data' => ['overridePath' => '/some/theme/Magento_Catalog/templates/product/view.phtml', 'coreModuleName' => 'Magento_Catalog', 'coreTemplatePath' => 'product/view.phtml']]]);
        $analyzer = new TemplateAnalyzer($this->fixtureDir . '/old-vendor', $this->fixtureDir . '/old-vendor');
        $this->assertCount(0, $analyzer->analyze($snapshot, $scan));
    }

    public function testNonExistentTemplateSkipped(): void
    {
        $snapshot = new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 1);
        $scan = new ScanResult('template', [['type' => 'template', 'data' => ['overridePath' => '/x.phtml', 'coreModuleName' => 'Magento_Catalog', 'coreTemplatePath' => 'nonexistent.phtml']]]);
        $analyzer = new TemplateAnalyzer($this->fixtureDir . '/old-vendor', $this->fixtureDir . '/new-vendor');
        $this->assertCount(0, $analyzer->analyze($snapshot, $scan));
    }
}
