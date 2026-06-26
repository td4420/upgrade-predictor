<?php

declare(strict_types=1);

namespace Bss\UpgradePredictor\Cli;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('M2 Upgrade Impact Predictor', '0.1.0');
        $this->add(new SnapshotCommand());
        $this->add(new AnalyzeCommand());
        $this->add(new ListCommand());
        $this->add(new CleanCommand());
    }
}
