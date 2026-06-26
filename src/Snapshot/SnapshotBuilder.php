<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Snapshot;

use Bss\UpgradePredictor\Model\Snapshot;

class SnapshotBuilder
{
    public function __construct(private readonly PhpClassMapper $mapper) {}

    public function build(
        string $currentVendorPath,
        string $targetVendorPath,
        string $fromVersion,
        string $toVersion,
        int $packageCount
    ): Snapshot {
        $oldClassMap = $this->mapper->mapDirectory($currentVendorPath);
        $newClassMap = $this->mapper->mapDirectory($targetVendorPath);

        return new Snapshot(
            fromVersion: $fromVersion,
            toVersion: $toVersion,
            oldClassMap: $oldClassMap,
            newClassMap: $newClassMap,
            createdAt: date('c'),
            packageCount: $packageCount,
        );
    }
}
