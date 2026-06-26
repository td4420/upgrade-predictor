<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Model;

class ClassMap
{
    public function __construct(private readonly array $classes) {}

    public function hasClass(string $className): bool { return isset($this->classes[$className]); }
    public function getMethods(string $className): ?array { return $this->classes[$className]['methods'] ?? null; }
    public function getMethod(string $className, string $methodName): ?array { return $this->classes[$className]['methods'][$methodName] ?? null; }
    public function getClassInfo(string $className): ?array { return $this->classes[$className] ?? null; }
    public function toArray(): array { return $this->classes; }
    public static function fromArray(array $data): self { return new self($data); }
}
