<?php

namespace App\Console\Commands;

use App\Services\VapidKeyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('push:vapid-keys {--force : Regenerate even when keys are already set}')]
#[Description('Generate a VAPID keypair and write it into .env (public/private are never committed)')]
class GenerateVapidKeys extends Command
{
    public function __construct(private readonly VapidKeyService $keys)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $env = base_path('.env');

        if (! is_file($env)) {
            $this->error('No .env file at '.$env.'. Run `cp .env.example .env` first.');

            return self::FAILURE;
        }

        if ($this->alreadyConfigured($env) && ! $this->option('force')) {
            $this->warn('VAPID keys already set in .env (use --force to regenerate).');

            return self::SUCCESS;
        }

        $pair = $this->keys->generate();

        $this->writeEnvValue($env, 'VAPID_PUBLIC_KEY', $pair['public_key']);
        $this->writeEnvValue($env, 'VAPID_PRIVATE_KEY', $pair['private_key']);

        // Keep the example file in sync so a fresh setup sees the placeholders.
        $this->writeEnvValue(base_path('.env.example'), 'VAPID_PUBLIC_KEY', '');
        $this->writeEnvValue(base_path('.env.example'), 'VAPID_PRIVATE_KEY', '');

        $this->info('Generated a VAPID keypair and wrote VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY to .env.');
        $this->info('Public key: '.$pair['public_key']);

        return self::SUCCESS;
    }

    private function alreadyConfigured(string $env): bool
    {
        $contents = (string) file_get_contents($env);

        return str_contains($contents, 'VAPID_PUBLIC_KEY=')
            && str_contains($contents, 'VAPID_PRIVATE_KEY=');
    }

    private function writeEnvValue(string $file, string $key, string $value): void
    {
        if (! is_file($file)) {
            return;
        }

        $contents = (string) file_get_contents($file);
        $escaped = preg_quote($key.'=', '/');
        $line = $key.'='.$value;

        if (preg_match('/^'.$escaped.'.*$/m', $contents, $matches) === 1) {
            $contents = str_replace($matches[0], $line, $contents);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($file, $contents);
    }
}
