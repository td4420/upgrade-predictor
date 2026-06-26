<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Model;

class Snapshot
{
    public function __construct(
        public readonly string $fromVersion,
        public readonly string $toVersion,
        public readonly ClassMap $oldClassMap,
        public readonly ClassMap $newClassMap,
        public readonly string $createdAt,
        public readonly int $packageCount,
    ) {}
}
