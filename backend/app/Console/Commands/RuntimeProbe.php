<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RuntimeProbe extends Command
{
    protected $signature = 'runtime:probe {--extensions : also dump every loaded extension}';

    protected $description = 'Report the PHP runtime and key loaded extensions (e.g. does Laravel Cloud ship libsql?).';

    public function handle(): int
    {
        $checks = [
            'libsql' => extension_loaded('libsql'),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'pdo_pgsql' => extension_loaded('pdo_pgsql'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'sodium' => extension_loaded('sodium'),
        ];

        $lines = ['PHP '.PHP_VERSION];
        foreach ($checks as $name => $loaded) {
            $lines[] = sprintf('%-10s %s', $name, $loaded ? 'yes' : 'no');
        }

        if ($this->option('extensions')) {
            $lines[] = '--- loaded extensions ---';
            $lines[] = implode(PHP_EOL, get_loaded_extensions());
        }

        $this->line(implode(PHP_EOL, $lines));

        info('runtime:probe', ['php' => PHP_VERSION, 'extensions' => $checks]);

        return self::SUCCESS;
    }
}
