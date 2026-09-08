<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Medication Server')]
#[Version('0.0.1')]
#[Instructions('Serves medication-manager domain tools: schedules, intakes, shift re-timing, and stock.')]
class MedicationServer extends Server
{
    protected array $tools = [
        \App\Mcp\Tools\AppHealthTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
