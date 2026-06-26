<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Scanner;

use Bss\UpgradePredictor\Scanner\DiXmlScanner;
use PHPUnit\Framework\TestCase;

class DiXmlScannerTest extends TestCase
{
    public function testScanFindsPreferences(): void
    {
        $scanner = new DiXmlScanner();
        $result = $scanner->scan([__DIR__ . '/../../Fixtures/xml']);
        $preferences = $result->filterByType('preference');
        $this->assertCount(2, $preferences->entries);
        $first = $preferences->entries[0]['data'];
        $this->assertSame('Magento\\Catalog\\Api\\Data\\ProductInterface', $first['originalClass']);
        $this->assertSame('Bss\\Custom\\Model\\Product', $first['customClass']);
    }

    public function testScanFindsPlugins(): void
    {
        $scanner = new DiXmlScanner();
        $result = $scanner->scan([__DIR__ . '/../../Fixtures/xml']);
        $plugins = $result->filterByType('plugin');
        $this->assertCount(2, $plugins->entries);
        $first = $plugins->entries[0]['data'];
        $this->assertSame('Magento\\Catalog\\Model\\Product', $first['targetClass']);
        $this->assertSame('Bss\\Custom\\Plugin\\ProductPlugin', $first['pluginClass']);
    }

    public function testScanEmptyDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/empty-xml-test-' . uniqid();
        mkdir($dir);
        $scanner = new DiXmlScanner();
        $result = $scanner->scan([$dir]);
        $this->assertCount(0, $result->entries);
        rmdir($dir);
    }
}
