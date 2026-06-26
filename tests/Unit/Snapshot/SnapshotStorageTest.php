<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Snapshot;

use Bss\UpgradePredictor\Model\ClassMap;
use Bss\UpgradePredictor\Model\Snapshot;
use Bss\UpgradePredictor\Snapshot\SnapshotStorage;
use PHPUnit\Framework\TestCase;

class SnapshotStorageTest extends TestCase
{
    private string $tempDir;
    private SnapshotStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/snapshot-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->storage = new SnapshotStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        // recursive delete
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        if (is_dir($this->tempDir)) rmdir($this->tempDir);
    }

    public function testSaveAndLoad(): void
    {
        $snapshot = new Snapshot('2.4.9', '2.4.10', new ClassMap(['Magento\\A' => ['file' => 'a.php', 'parent' => null, 'interfaces' => [], 'methods' => []]]), new ClassMap(['Magento\\A' => ['file' => 'a.php', 'parent' => null, 'interfaces' => [], 'methods' => []]]), '2026-06-26T10:00:00Z', 156);
        $this->storage->save($snapshot);
        $loaded = $this->storage->load('2.4.10');
        $this->assertSame('2.4.9', $loaded->fromVersion);
        $this->assertSame('2.4.10', $loaded->toVersion);
        $this->assertSame(156, $loaded->packageCount);
        $this->assertTrue($loaded->oldClassMap->hasClass('Magento\\A'));
    }

    public function testListSnapshots(): void
    {
        $this->assertSame([], $this->storage->listSnapshots());
        $this->storage->save(new Snapshot('2.4.8', '2.4.9', new ClassMap([]), new ClassMap([]), '2026-06-25T10:00:00Z', 150));
        $this->storage->save(new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 156));
        $list = $this->storage->listSnapshots();
        $this->assertCount(2, $list);
        $this->assertSame('2.4.9', $list[0]['version']);
        $this->assertSame('2.4.10', $list[1]['version']);
    }

    public function testLoadLatest(): void
    {
        $this->storage->save(new Snapshot('2.4.8', '2.4.9', new ClassMap([]), new ClassMap([]), '2026-06-25T10:00:00Z', 150));
        $this->storage->save(new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 156));
        $latest = $this->storage->loadLatest();
        $this->assertSame('2.4.10', $latest->toVersion);
    }

    public function testDelete(): void
    {
        $this->storage->save(new Snapshot('2.4.9', '2.4.10', new ClassMap([]), new ClassMap([]), '2026-06-26T10:00:00Z', 156));
        $this->assertCount(1, $this->storage->listSnapshots());
        $this->storage->delete('2.4.10');
        $this->assertCount(0, $this->storage->listSnapshots());
    }

    public function testLoadNonExistentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->storage->load('9.9.9');
    }
}
