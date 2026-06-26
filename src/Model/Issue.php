<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Model;

class Issue
{
    public function __construct(
        public readonly Severity $severity,
        public readonly string $analyzer,
        public readonly string $sourceFile,
        public readonly ?int $sourceLine,
        public readonly string $targetClass,
        public readonly string $message,
        public readonly ?string $diff = null,
        public readonly ?string $suggestion = null,
    ) {}
}
