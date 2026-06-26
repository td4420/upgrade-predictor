<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Analyzer;

use Bss\UpgradePredictor\Analyzer\PluginAnalyzer;
use Bss\UpgradePredictor\Model\ClassMap;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;
use PHPUnit\Framework\TestCase;

class PluginAnalyzerTest extends TestCase
{
    private function snap(array $old, array $new): Snapshot
    {
        return new Snapshot('2.4.9', '2.4.10', new ClassMap($old), new ClassMap($new), '2026-06-26T10:00:00Z', 1);
    }
    private function cls(array $methods): array
    {
        return ['file' => 'test.php', 'parent' => null, 'interfaces' => [], 'methods' => $methods];
    }
    private function pluginScan(array $plugins): ScanResult
    {
        $entries = [];
        foreach ($plugins as [$target, $plugin, $methods]) {
            $entries[] = ['type' => 'plugin', 'data' => ['targetClass' => $target, 'pluginClass' => $plugin, 'pluginMethods' => $methods, 'diXmlFile' => 'di.xml', 'diXmlLine' => 1]];
        }
        return new ScanResult('di-xml', $entries);
    }

    public function testTargetClassRemovedIsCritical(): void
    {
        $snap = $this->snap(['Magento\\Catalog\\Model\\Product' => $this->cls([])], []);
        $scan = $this->pluginScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Plugin\\ProductPlugin', ['aroundGetName']]]);
        $issues = (new PluginAnalyzer())->analyze($snap, $scan);
        $this->assertCount(1, $issues);
        $this->assertSame(Severity::CRITICAL, $issues->all()[0]->severity);
    }

    public function testAroundPluginParamCountChangedIsCritical(): void
    {
        $old = ['setPrice' => ['params' => [['name' => 'price', 'type' => 'float', 'default' => false]], 'returnType' => 'self', 'visibility' => 'public']];
        $new = ['setPrice' => ['params' => [['name' => 'price', 'type' => 'float', 'default' => false], ['name' => 'tax', 'type' => 'bool', 'default' => false]], 'returnType' => 'self', 'visibility' => 'public']];
        $snap = $this->snap(['Magento\\Catalog\\Model\\Product' => $this->cls($old)], ['Magento\\Catalog\\Model\\Product' => $this->cls($new)]);
        $scan = $this->pluginScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Plugin\\ProductPlugin', ['aroundSetPrice']]]);
        $issues = (new PluginAnalyzer())->analyze($snap, $scan);
        $this->assertSame(Severity::CRITICAL, $issues->all()[0]->severity);
    }

    public function testBeforePluginParamCountChangedIsCritical(): void
    {
        $old = ['save' => ['params' => [], 'returnType' => 'void', 'visibility' => 'public']];
        $new = ['save' => ['params' => [['name' => 'flush', 'type' => 'bool', 'default' => false]], 'returnType' => 'void', 'visibility' => 'public']];
        $snap = $this->snap(['Magento\\Catalog\\Model\\Product' => $this->cls($old)], ['Magento\\Catalog\\Model\\Product' => $this->cls($new)]);
        $scan = $this->pluginScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Plugin\\ProductPlugin', ['beforeSave']]]);
        $critical = (new PluginAnalyzer())->analyze($snap, $scan)->filterBySeverity(Severity::CRITICAL);
        $this->assertGreaterThanOrEqual(1, count($critical));
    }

    public function testAfterPluginParamCountChangedIsWarning(): void
    {
        $old = ['getName' => ['params' => [], 'returnType' => 'string', 'visibility' => 'public']];
        $new = ['getName' => ['params' => [['name' => 'storeId', 'type' => 'int', 'default' => false]], 'returnType' => 'string', 'visibility' => 'public']];
        $snap = $this->snap(['Magento\\Catalog\\Model\\Product' => $this->cls($old)], ['Magento\\Catalog\\Model\\Product' => $this->cls($new)]);
        $scan = $this->pluginScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Plugin\\ProductPlugin', ['afterGetName']]]);
        $warning = (new PluginAnalyzer())->analyze($snap, $scan)->filterBySeverity(Severity::WARNING);
        $this->assertGreaterThanOrEqual(1, count($warning));
    }

    public function testMethodRemovedPluginNeverCalledIsCritical(): void
    {
        $old = ['oldMethod' => ['params' => [], 'returnType' => 'void', 'visibility' => 'public']];
        $snap = $this->snap(['Magento\\Catalog\\Model\\Product' => $this->cls($old)], ['Magento\\Catalog\\Model\\Product' => $this->cls([])]);
        $scan = $this->pluginScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Plugin\\ProductPlugin', ['aroundOldMethod']]]);
        $issues = (new PluginAnalyzer())->analyze($snap, $scan);
        $this->assertSame(Severity::CRITICAL, $issues->all()[0]->severity);
    }

    public function testNoChangeProducesNoIssues(): void
    {
        $methods = ['getName' => ['params' => [], 'returnType' => 'string', 'visibility' => 'public']];
        $snap = $this->snap(['Magento\\Catalog\\Model\\Product' => $this->cls($methods)], ['Magento\\Catalog\\Model\\Product' => $this->cls($methods)]);
        $scan = $this->pluginScan([['Magento\\Catalog\\Model\\Product', 'Bss\\Plugin\\ProductPlugin', ['afterGetName']]]);
        $this->assertCount(0, (new PluginAnalyzer())->analyze($snap, $scan));
    }
}
