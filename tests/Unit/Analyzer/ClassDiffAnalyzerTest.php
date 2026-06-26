<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Analyzer;

use Bss\UpgradePredictor\Analyzer\ClassDiffAnalyzer;
use Bss\UpgradePredictor\Model\ClassMap;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;
use PHPUnit\Framework\TestCase;

class ClassDiffAnalyzerTest extends TestCase
{
    private function makeSnapshot(array $old, array $new): Snapshot
    {
        return new Snapshot('2.4.9', '2.4.10', new ClassMap($old), new ClassMap($new), '2026-06-26T10:00:00Z', 1);
    }

    private function cls(array $methods, ?string $parent = null): array
    {
        return ['file' => 'test.php', 'parent' => $parent, 'interfaces' => [], 'methods' => $methods];
    }

    private function prefScan(array $prefs): ScanResult
    {
        $entries = [];
        foreach ($prefs as [$orig, $custom]) {
            $entries[] = ['type' => 'preference', 'data' => ['originalClass' => $orig, 'customClass' => $custom, 'diXmlFile' => 'di.xml', 'diXmlLine' => 1]];
        }
        return new ScanResult('di-xml', $entries);
    }

    public function testClassRemovedIsCritical(): void
    {
        $snapshot = $this->makeSnapshot(['Magento\\Catalog\\Model\\Product' => $this->cls([])], []);
        $scan = $this->prefScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Custom\\Model\\Product']]);
        $issues = (new ClassDiffAnalyzer())->analyze($snapshot, $scan);
        $this->assertCount(1, $issues);
        $this->assertSame(Severity::CRITICAL, $issues->all()[0]->severity);
        $this->assertStringContainsString('removed', $issues->all()[0]->message);
    }

    public function testMethodParamCountChangedIsCritical(): void
    {
        $old = ['save' => ['params' => [['name' => 'entity', 'type' => 'mixed', 'default' => false]], 'returnType' => 'self', 'visibility' => 'public']];
        $new = ['save' => ['params' => [['name' => 'entity', 'type' => 'mixed', 'default' => false], ['name' => 'flush', 'type' => 'bool', 'default' => false]], 'returnType' => 'self', 'visibility' => 'public']];
        $snapshot = $this->makeSnapshot(['Magento\\Catalog\\Model\\Product' => $this->cls($old)], ['Magento\\Catalog\\Model\\Product' => $this->cls($new)]);
        $scan = $this->prefScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Custom\\Model\\Product']]);
        $issues = (new ClassDiffAnalyzer())->analyze($snapshot, $scan);
        $this->assertGreaterThanOrEqual(1, count($issues->filterBySeverity(Severity::CRITICAL)));
    }

    public function testNoChangeProducesNoIssues(): void
    {
        $methods = ['getName' => ['params' => [], 'returnType' => '?string', 'visibility' => 'public']];
        $snapshot = $this->makeSnapshot(['Magento\\Catalog\\Model\\Product' => $this->cls($methods)], ['Magento\\Catalog\\Model\\Product' => $this->cls($methods)]);
        $scan = $this->prefScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Custom\\Model\\Product']]);
        $this->assertCount(0, (new ClassDiffAnalyzer())->analyze($snapshot, $scan));
    }

    public function testMethodRemovedIsCritical(): void
    {
        $old = ['save' => ['params' => [], 'returnType' => 'self', 'visibility' => 'public']];
        $snapshot = $this->makeSnapshot(['Magento\\Catalog\\Model\\Product' => $this->cls($old)], ['Magento\\Catalog\\Model\\Product' => $this->cls([])]);
        $scan = $this->prefScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Custom\\Model\\Product']]);
        $issues = (new ClassDiffAnalyzer())->analyze($snapshot, $scan);
        $this->assertSame(Severity::CRITICAL, $issues->all()[0]->severity);
    }

    public function testReturnTypeChangedIsWarning(): void
    {
        $old = ['getName' => ['params' => [], 'returnType' => 'string', 'visibility' => 'public']];
        $new = ['getName' => ['params' => [], 'returnType' => '?string', 'visibility' => 'public']];
        $snapshot = $this->makeSnapshot(['Magento\\Catalog\\Model\\Product' => $this->cls($old)], ['Magento\\Catalog\\Model\\Product' => $this->cls($new)]);
        $scan = $this->prefScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Custom\\Model\\Product']]);
        $issues = (new ClassDiffAnalyzer())->analyze($snapshot, $scan);
        $this->assertCount(1, $issues);
        $this->assertSame(Severity::WARNING, $issues->all()[0]->severity);
    }
}
