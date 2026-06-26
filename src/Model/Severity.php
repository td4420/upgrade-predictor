<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Model;

enum Severity: string
{
    case CRITICAL = 'critical';
    case WARNING = 'warning';
    case INFO = 'info';
}
