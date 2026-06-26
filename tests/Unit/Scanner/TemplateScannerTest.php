<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Scanner;

use Bss\UpgradePredictor\Scanner\TemplateScanner;
use PHPUnit\Framework\TestCase;

class TemplateScannerTest extends TestCase
{
    public function testFindsTemplateOverrides(): void
    {
        $projectRoot = __DIR__ . '/../../Fixtures/template-project';
        $scanner = new TemplateScanner($projectRoot);
        $result = $scanner->scan();
        $this->assertGreaterThanOrEqual(1, count($result->entries));
        $entry = $result->entries[0]['data'];
        $this->assertSame('Magento_Catalog', $entry['coreModuleName']);
        $this->assertSame('product/view.phtml', $entry['coreTemplatePath']);
        $this->assertStringContainsString('Magento_Catalog/templates/product/view.phtml', $entry['overridePath']);
    }

    public function testEmptyDesignDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/empty-design-test-' . uniqid();
        mkdir($dir);
        $scanner = new TemplateScanner($dir);
        $result = $scanner->scan();
        $this->assertCount(0, $result->entries);
        rmdir($dir);
    }
}
