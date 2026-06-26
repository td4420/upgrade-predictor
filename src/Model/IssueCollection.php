<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Model;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class IssueCollection implements Countable, IteratorAggregate
{
    /** @param list<Issue> $issues */
    public function __construct(private readonly array $issues = []) {}

    public function count(): int { return count($this->issues); }
    public function getIterator(): Traversable { return new ArrayIterator($this->issues); }

    public function filterBySeverity(Severity $severity): self
    {
        return new self(array_values(array_filter($this->issues, fn(Issue $i) => $i->severity === $severity)));
    }

    public function filterByAnalyzer(string $analyzer): self
    {
        return new self(array_values(array_filter($this->issues, fn(Issue $i) => $i->analyzer === $analyzer)));
    }

    /** @return array{critical: int, warning: int, info: int} */
    public function summary(): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($this->issues as $issue) { $counts[$issue->severity->value]++; }
        return $counts;
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->issues, $other->issues));
    }

    /** @return list<Issue> */
    public function all(): array { return $this->issues; }
}
