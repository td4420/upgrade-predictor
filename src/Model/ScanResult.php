<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Model;

class ScanResult
{
    /** @param list<array{type: string, data: array<string, mixed>}> $entries */
    public function __construct(
        public readonly string $scanner,
        public readonly array $entries,
    ) {}

    public function filterByType(string $type): self
    {
        return new self($this->scanner, array_values(array_filter($this->entries, fn(array $e) => $e['type'] === $type)));
    }
}
