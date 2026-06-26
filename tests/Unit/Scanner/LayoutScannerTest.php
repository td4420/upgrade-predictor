<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Scanner;

use Bss\UpgradePredictor\Scanner\LayoutScanner;
use PHPUnit\Framework\TestCase;

class LayoutScannerTest extends TestCase
{
    public function testFindsReferenceBlocksAndContainers(): void
    {
        $scanner = new LayoutScanner();
        $result = $scanner->scan([__DIR__ . '/../../Fixtures/xml/layout']);
        $refs = array_values(array_filter($result->entries, fn($e) => $e['type'] === 'reference'));
        $this->assertCount(2, $refs);
        $this->assertSame('product.info.main', $refs[0]['data']['referenceName']);
        $this->assertSame('block', $refs[0]['data']['referenceType']);
        $this->assertSame('content', $refs[1]['data']['referenceName']);
        $this->assertSame('container', $refs[1]['data']['referenceType']);
    }

    public function testFindsMoveElements(): void
    {
        $scanner = new LayoutScanner();
        $result = $scanner->scan([__DIR__ . '/../../Fixtures/xml/layout']);
        $moves = array_values(array_filter($result->entries, fn($e) => $e['type'] === 'move'));
        $this->assertCount(1, $moves);
        $this->assertSame('product.info.price', $moves[0]['data']['element']);
        $this->assertSame('product.info.main', $moves[0]['data']['destination']);
    }

    public function testScanEmptyDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/empty-layout-test-' . uniqid();
        mkdir($dir);
        $scanner = new LayoutScanner();
        $result = $scanner->scan([$dir]);
        $this->assertCount(0, $result->entries);
        rmdir($dir);
    }
}
