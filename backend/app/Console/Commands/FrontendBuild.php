<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[Signature('frontend:build')]
#[Description("Build the Angular app and publish its dist into Laravel's public path (single-origin serving)")]
class FrontendBuild extends Command
{
    /**
     * Laravel-owned files/directories in public/ that must never be touched
     * when frontend output is cleaned or published. Everything else at the
     * public root is treated as Angular-managed build output.
     *
     * @var array<int, string>
     */
    private const LARAVEL_OWNED = ['index.php', '.htaccess', 'robots.txt', 'build'];

    public function handle(): int
    {
        $appDir = base_path('../app');

        if (! is_dir($appDir.'/node_modules')) {
            $this->components->error("Angular dependencies not installed. Run `npm ci` in {$appDir} first.");

            return static::FAILURE;
        }

        if (File::exists($appDir.'/package-lock.json')) {
            $this->components->info('Installing Angular dependencies (npm ci)...');
            $this->runProcess(['npm', 'ci'], $appDir);
        }

        $this->components->info('Building Angular app (ng build)...');
        $this->runProcess(['npm', 'run', 'build', '--', '--configuration=production'], $appDir);

        $source = $appDir.'/dist/app/browser';

        if (! is_dir($source)) {
            $this->components->error("Angular build output not found at {$source}.");

            return static::FAILURE;
        }

        $this->publishFrontend($source, public_path());

        $this->components->info('Single-origin build complete. The SPA is served at the root and /api continues to serve the API.');

        return static::SUCCESS;
    }

    private function runProcess(array $command, string $cwd): void
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(null);
        $process->run(function (string $type, string $line): void {
            $this->line(trim($line));
        });

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Replace the Angular-managed frontend output in Laravel's public path,
     * preserving Laravel-owned files/directories.
     */
    private function publishFrontend(string $source, string $destination): void
    {
        foreach (File::files($destination) as $file) {
            $name = $file->getFilename();

            if (! in_array($name, self::LARAVEL_OWNED, true)) {
                File::delete($file->getRealPath());
            }
        }

        foreach (File::directories($destination) as $directory) {
            $name = basename($directory);

            if (! in_array($name, self::LARAVEL_OWNED, true)) {
                File::deleteDirectory($directory, false);
            }
        }

        $this->copyDirectory($source, $destination);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        foreach (File::files($source) as $file) {
            File::copy($file->getRealPath(), $destination.'/'.$file->getFilename());
        }

        foreach (File::directories($source) as $directory) {
            $name = basename($directory);
            $destinationDir = $destination.'/'.$name;

            if (! is_dir($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true);
            }

            $this->copyDirectory($directory, $destinationDir);
        }
    }
}
