<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RestartServices extends Command
{
    protected $signature = 'app:restart-services';

    protected $description = 'Restart production application services';

    public function handle(): int
    {
        if (! app()->isProduction()) {
            $this->components->info('Skipping service restart outside production.');

            return self::SUCCESS;
        }

        $services = [
            'php8.5-fpm.service',
        ];

        foreach ($services as $service) {
            $this->components->info("Restarting {$service}...");

            $process = new Process([
                'sudo',
                '-n',
                '/usr/bin/systemctl',
                'restart',
                $service,
            ]);

            $process->run();

            if (! $process->isSuccessful()) {
                $this->components->error(
                    $process->getErrorOutput() ?: $process->getOutput()
                );

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
