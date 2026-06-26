<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Config;

class Config
{
    /** @var list<string> */
    public readonly array $scanPaths;
    /** @var list<string> */
    public readonly array $extraVendors;
    public readonly string $snapshotDir;
    public readonly string $output;
    public readonly string $failOn;
    /** @var list<string> */
    public readonly array $ignore;

    private function __construct(array $data)
    {
        $this->scanPaths = $data['scan_paths'];
        $this->extraVendors = $data['extra_vendors'];
        $this->snapshotDir = $data['snapshot_dir'];
        $this->output = $data['output'];
        $this->failOn = $data['fail_on'];
        $this->ignore = $data['ignore'];
    }

    public static function load(string $projectDir, ?string $configPath = null): self
    {
        $defaultFile = dirname(__DIR__, 2) . '/config/default.json';
        $defaults = json_decode(file_get_contents($defaultFile), true);

        $userFile = $configPath ?? $projectDir . '/upgrade-predictor.json';
        $user = [];
        if (file_exists($userFile)) {
            $content = file_get_contents($userFile);
            $user = json_decode($content, true) ?? [];
        }

        return new self(array_merge($defaults, $user));
    }
}
