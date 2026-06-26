<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Snapshot;

use Bss\UpgradePredictor\Model\ClassMap;
use Bss\UpgradePredictor\Model\Snapshot;

class SnapshotStorage
{
    public function __construct(private readonly string $storageDir) {}

    public function save(Snapshot $snapshot): void
    {
        $dir = $this->versionDir($snapshot->toVersion);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $meta = [
            'from_version' => $snapshot->fromVersion,
            'to_version' => $snapshot->toVersion,
            'created_at' => $snapshot->createdAt,
            'package_count' => $snapshot->packageCount,
        ];

        file_put_contents($dir . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT));
        file_put_contents($dir . '/class-map-old.json', json_encode($snapshot->oldClassMap->toArray(), JSON_PRETTY_PRINT));
        file_put_contents($dir . '/class-map-new.json', json_encode($snapshot->newClassMap->toArray(), JSON_PRETTY_PRINT));
    }

    public function load(string $version): Snapshot
    {
        $dir = $this->versionDir($version);

        if (!is_dir($dir) || !file_exists($dir . '/meta.json')) {
            throw new \RuntimeException("Snapshot for version '{$version}' not found in {$this->storageDir}");
        }

        $meta = json_decode(file_get_contents($dir . '/meta.json'), true);
        $oldClasses = json_decode(file_get_contents($dir . '/class-map-old.json'), true);
        $newClasses = json_decode(file_get_contents($dir . '/class-map-new.json'), true);

        return new Snapshot(
            $meta['from_version'],
            $meta['to_version'],
            new ClassMap($oldClasses),
            new ClassMap($newClasses),
            $meta['created_at'],
            (int) $meta['package_count'],
        );
    }

    public function loadLatest(): Snapshot
    {
        $list = $this->listSnapshots();

        if (empty($list)) {
            throw new \RuntimeException("No snapshots found in {$this->storageDir}");
        }

        // Sort by version_compare, take the highest
        usort($list, fn($a, $b) => version_compare($a['version'], $b['version']));
        $latest = end($list);

        return $this->load($latest['version']);
    }

    /**
     * @return list<array{version: string, from_version: string, created_at: string, package_count: int}>
     */
    public function listSnapshots(): array
    {
        if (!is_dir($this->storageDir)) {
            return [];
        }

        $snapshots = [];

        foreach (new \DirectoryIterator($this->storageDir) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }
            $metaFile = $entry->getPathname() . '/meta.json';
            if (!file_exists($metaFile)) {
                continue;
            }
            $meta = json_decode(file_get_contents($metaFile), true);
            $snapshots[] = [
                'version' => $meta['to_version'],
                'from_version' => $meta['from_version'],
                'created_at' => $meta['created_at'],
                'package_count' => (int) $meta['package_count'],
            ];
        }

        // Sort by version_compare ascending
        usort($snapshots, fn($a, $b) => version_compare($a['version'], $b['version']));

        return $snapshots;
    }

    public function delete(string $version): void
    {
        $dir = $this->versionDir($version);

        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    public function has(string $version): bool
    {
        return is_dir($this->versionDir($version)) && file_exists($this->versionDir($version) . '/meta.json');
    }

    private function versionDir(string $version): string
    {
        return $this->storageDir . '/' . $version;
    }
}
