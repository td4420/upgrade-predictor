<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Snapshot;

use Bss\UpgradePredictor\Model\ClassMap;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class PhpClassMapper
{
    public function mapDirectory(string $directory): ClassMap
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $allClasses = [];

        if (!is_dir($directory)) {
            return new ClassMap([]);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();

            // Accept .php and .php.txt files
            if (!preg_match('/\.php(\.txt)?$/', $path)) {
                continue;
            }

            // Skip Test/ directories
            if (preg_match('#[/\\\\]Tests?[/\\\\]#', $path)) {
                continue;
            }

            $code = file_get_contents($path);

            // Quick pre-filter: skip files without class/interface
            if (!preg_match('/\b(class|interface|trait)\s+\w+/i', $code)) {
                continue;
            }

            try {
                $stmts = $parser->parse($code);
                if ($stmts === null) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }

            $visitor = new ClassCollectorVisitor($path);

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($stmts);

            foreach ($visitor->getCollected() as $fqn => $info) {
                $allClasses[$fqn] = $info;
            }
        }

        return new ClassMap($allClasses);
    }
}

/**
 * @internal
 */
class ClassCollectorVisitor extends NodeVisitorAbstract
{
    private string $currentNamespace = '';
    private array $collected = [];
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function getCollected(): array
    {
        return $this->collected;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : '';
        }

        if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Interface_) {
            $name = $node->name ? $node->name->toString() : null;
            if ($name === null) {
                return null;
            }
            $fqn = $this->currentNamespace ? $this->currentNamespace . '\\' . $name : $name;

            $parent = null;
            $interfaces = [];

            if ($node instanceof Stmt\Class_) {
                if ($node->extends !== null) {
                    $parent = $this->resolveNameFromContext($node->extends->toString());
                }
                foreach ($node->implements as $iface) {
                    $interfaces[] = $this->resolveNameFromContext($iface->toString());
                }
            } elseif ($node instanceof Stmt\Interface_) {
                foreach ($node->extends as $ext) {
                    $interfaces[] = $this->resolveNameFromContext($ext->toString());
                }
            }

            $methods = [];
            foreach ($node->getMethods() as $method) {
                // Skip private methods
                if ($method->flags & Modifiers::PRIVATE) {
                    continue;
                }

                $params = [];
                foreach ($method->params as $param) {
                    $paramName = $param->var instanceof Node\Expr\Variable
                        ? (string) $param->var->name
                        : '';
                    $params[] = [
                        'name' => $paramName,
                        'type' => $this->resolveType($param->type),
                        'hasDefault' => $param->default !== null,
                    ];
                }

                $methods[$method->name->toString()] = [
                    'params' => $params,
                    'returnType' => $this->resolveType($method->returnType),
                ];
            }

            $this->collected[$fqn] = [
                'file' => $this->file,
                'parent' => $parent,
                'interfaces' => $interfaces,
                'methods' => $methods,
            ];
        }

        return null;
    }

    private function resolveNameFromContext(string $name): string
    {
        // If fully qualified (starts with \) — strip leading backslash
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        // If single name segment and we have a namespace, prepend it
        if ($this->currentNamespace !== '' && !str_contains($name, '\\')) {
            return $this->currentNamespace . '\\' . $name;
        }
        return $name;
    }

    private function resolveType(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof Node\NullableType) {
            $inner = $this->resolveType($type->type);
            return $inner !== null ? '?' . $inner : null;
        }
        if ($type instanceof Node\UnionType) {
            return implode('|', array_map(fn($t) => $this->resolveType($t) ?? '', $type->types));
        }
        if ($type instanceof Node\IntersectionType) {
            return implode('&', array_map(fn($t) => $this->resolveType($t) ?? '', $type->types));
        }
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }
        if ($type instanceof Node\Name) {
            return $type->toString();
        }
        return null;
    }
}
