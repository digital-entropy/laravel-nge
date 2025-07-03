<?php

namespace Dentro\Nge\Console;

use Illuminate\Console\Command;
use Dentro\Nge\Console\Concerns\InteractsWithDockerComposeServices;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'nge:add')]
class AddCommand extends Command
{
    use InteractsWithDockerComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nge:add
        {services? : The services that should be added}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '(alpha) Add a service to an existing Nge installation';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $services = $this->gatherServicesInteractively();

        if ($invalidServices = array_diff($services, $this->services)) {
            $this->components->error('Invalid services [' . implode(',', $invalidServices) . '].');

            return;
        }

        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->configurePhpUnit();

        $this->prepareInstallation($services);

        $this->output->writeln('');
        $this->components->info('Additional Nge services installed successfully.');
    }
}
