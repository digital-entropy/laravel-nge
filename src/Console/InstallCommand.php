<?php

namespace Dentro\Nge\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'nge:install')]
class InstallCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nge:install
                {--php=8.3 : The PHP version that should be used}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Nge\'s Docker Compose file';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('no-interaction')) {
            $services = $this->defaultServices;
        } else {
            $services = $this->gatherServicesInteractively();
        }

        if ($invalidServices = array_diff($services, $this->services)) {
            $this->components->error('Invalid services [' . implode(',', $invalidServices) . '].');

            return;
        }

        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->configurePhpUnit();

        $this->prepareInstallation($services);

        $this->output->writeln('');
        $this->components->info('Nge scaffolding installed successfully. You may run your Docker containers using Nge\'s "up" command.');

        $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/nge up</>');

        if (
            in_array('mysql', $services) ||
            in_array('mariadb', $services) ||
            in_array('pgsql', $services)
        ) {
            $this->components->warn('A database service was installed. Run "artisan migrate" to prepare your database:');

            $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/nge artisan migrate</>');
        }

        $this->output->writeln('');
    }
}
