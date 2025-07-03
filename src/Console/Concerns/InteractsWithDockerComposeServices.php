<?php

namespace Dentro\Nge\Console\Concerns;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

trait InteractsWithDockerComposeServices
{
    /**
     * The available services that may be installed.
     *
     * @var array<string>
     */
    protected $services = [
        'mysql',
        'pgsql',
        'mariadb',
        'redis',
        'mailpit',
        'soketi',
    ];

    /**
     * The default services used when the user chooses non-interactive mode.
     *
     * @var string[]
     */
    protected $defaultServices = ['mysql', 'redis', 'mailpit'];

    /**
     * Gather the desired Nge services using an interactive prompt.
     *
     * @return array
     */
    protected function gatherServicesInteractively()
    {
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which services would you like to install?',
                options: $this->services,
                default: ['mysql'],
            );
        }

        return $this->choice('Which services would you like to install?', $this->services, 0, null, true);
    }

    /**
     * Build the Docker Compose file.
     *
     * @param array $services
     * @return void
     */
    protected function buildDockerCompose(array $services)
    {
        $appName = str()->slug(config('app.name'));
        $appUser = get_current_user();
        $composePath = base_path('docker-compose.yml');

        $composeContent = file_get_contents(__DIR__ . '/../../../stubs/docker-compose.stub');
        $composeContent = str_replace('NGE_HOSTNAME:-nge-hostname', "NGE_HOSTNAME:-$appName-hostname", $composeContent);
        $composeContent = str_replace('dentro/nge:latest', "$appName/app:latest", $composeContent);
        $composeContent = str_replace('NGE_USER:-enji', "NGE_USER:-$appUser", $composeContent);

        $existingCompose = file_exists($composePath);
        $compose = $existingCompose
            ? Yaml::parseFile($composePath)
            : Yaml::parse($composeContent);

        if ($existingCompose) {
            $this->info('Found existing docker-compose.yml file. Merging with new services...');
            $this->warn('If you want to reset docker-compose.yml, remove it first! And run this command again.');
        } else {
            $this->info('Creating new docker-compose.yml file with the selected services...');
        }

        // Adds the new services as dependencies of the core service...
        if (!array_key_exists('core', $compose['services'])) {
            $this->warn('Couldn\'t find the --core-- service. Make sure you add [' . implode(',', $services) . '] in depends_on attribute.');
        } else {
            $dependsOnServices = collect(data_get($compose, 'services.core.depends_on', []))
                ->merge($services)
                ->unique()
                ->values()
                ->all();

            data_set($compose, 'services.core.depends_on', $dependsOnServices);
        }

        // Add the services to the docker-compose.yml...
        collect($services)
            ->filter(function ($service) use ($compose) {
                return !array_key_exists($service, data_get($compose, 'services', []));
            })->each(function ($service) use (&$compose) {
                $serviceConfig = Yaml::parseFile(__DIR__ . "/../../../stubs/$service.stub");
                data_set(
                    $compose,
                    "services.$service",
                    data_get($serviceConfig, $service),
                );
            });

        // Merge volumes...
        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'redis']);
            })->filter(function ($service) use ($compose) {
                return !array_key_exists($service, data_get($compose, 'volumes', []));
            })->each(function ($service) use (&$compose) {
                data_set($compose, "volumes.$service-store", ['driver' => 'local']);
            });

        // If the list of volumes is empty, we can remove it...
        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $yaml = Yaml::dump($compose, 4, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $yaml = str_replace('{{PHP_VERSION}}', $this->hasOption('php') ? $this->option('php') : '8.3', $yaml);

        file_put_contents($this->laravel->basePath('docker-compose.yml'), $yaml);
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     *
     * @param array $services
     * @return void
     */
    protected function replaceEnvVariables(array $services)
    {
        $environment = file_get_contents($this->laravel->basePath('.env'));

        if (file_exists($this->laravel->basePath('.env.backup'))) {
            $this->warn('.env.backup already exists! Please remove it before running this command again.');
            exit(0);
        }

        if (!file_exists($this->laravel->basePath('.env.backup'))) {
            copy($this->laravel->basePath('.env'), $this->laravel->basePath('.env.backup'));
        }

        if (
            in_array('mysql', $services) ||
            in_array('mariadb', $services) ||
            in_array('pgsql', $services)
        ) {
            $defaults = [
                '# DB_HOST=127.0.0.1',
                '# DB_PORT=3306',
                '# DB_DATABASE=laravel',
                '# DB_USERNAME=root',
                '# DB_PASSWORD=',
            ];

            foreach ($defaults as $default) {
                $environment = str_replace($default, substr($default, 2), $environment);
            }
        }

        if (in_array('mysql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            $environment = preg_replace('/DB_HOST=.*/', "DB_HOST=mysql", $environment);
            $environment = preg_replace('/DB_PORT=.*/', "DB_PORT=3306", $environment);
        } elseif (in_array('pgsql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $environment);
            $environment = preg_replace('/DB_HOST=.*/', "DB_HOST=pgsql", $environment);
            $environment = preg_replace('/DB_PORT=.*/', "DB_PORT=5432", $environment);
        } elseif (in_array('mariadb', $services)) {
            if ($this->laravel->config->has('database.connections.mariadb')) {
                $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mariadb', $environment);
            } else {
                // fallback to use mysql driver if mariadb connection is not defined.
                $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            }

            $environment = preg_replace('/DB_HOST=.*/', "DB_HOST=mariadb", $environment);
            $environment = preg_replace('/DB_PORT=.*/', "DB_PORT=3306", $environment);
        }

        $randomPassword = str()->random(16);
        $environment = str_replace('DB_USERNAME=root', "DB_USERNAME=app", $environment);
        $environment = preg_replace("/DB_PASSWORD=(.*)/", "DB_PASSWORD=$randomPassword", $environment);

        if (in_array('redis', $services)) {
            $environment = preg_replace('/REDIS_HOST=.*/', 'REDIS_HOST=redis', $environment);
        }

        if (in_array('soketi', $services)) {
            $randomAppId = str()->random(16);
            $randomAppKey = str()->random(32);
            $randomAppSecret = str()->random(32);

            $environment = preg_replace("/^BROADCAST_DRIVER=(.*)/m", "BROADCAST_DRIVER=pusher", $environment);
            $environment = preg_replace("/^PUSHER_APP_ID=(.*)/m", "PUSHER_APP_ID=$randomAppId", $environment);
            $environment = preg_replace("/^PUSHER_APP_KEY=(.*)/m", "PUSHER_APP_KEY=$randomAppKey", $environment);
            $environment = preg_replace("/^PUSHER_APP_SECRET=(.*)/m", "PUSHER_APP_SECRET=$randomAppSecret", $environment);
            $environment = preg_replace("/^PUSHER_HOST=(.*)/m", "PUSHER_HOST=soketi", $environment);
            $environment = preg_replace("/^PUSHER_PORT=(.*)/m", "PUSHER_PORT=6001", $environment);
            $environment = preg_replace("/^PUSHER_SCHEME=(.*)/m", "PUSHER_SCHEME=http", $environment);
            $environment = preg_replace("/^VITE_PUSHER_HOST=(.*)/m", "VITE_PUSHER_HOST=localhost", $environment);
        }

        if (in_array('mailpit', $services)) {
            $environment = preg_replace("/^MAIL_MAILER=(.*)/m", "MAIL_MAILER=smtp", $environment);
            $environment = preg_replace("/^MAIL_HOST=(.*)/m", "MAIL_HOST=mailpit", $environment);
            $environment = preg_replace("/^MAIL_PORT=(.*)/m", "MAIL_PORT=1025", $environment);
        }

        file_put_contents($this->laravel->basePath('.env'), $environment);
    }

    /**
     * Configure PHPUnit to use the dedicated testing database.
     *
     * @return void
     */
    protected function configurePhpUnit()
    {
        if (!file_exists($path = $this->laravel->basePath('phpunit.xml'))) {
            $path = $this->laravel->basePath('phpunit.xml.dist');

            if (!file_exists($path)) {
                return;
            }
        }

        $phpunit = file_get_contents($path);

        $phpunit = preg_replace('/^.*DB_CONNECTION.*\n/m', '', $phpunit);
        $phpunit = str_replace('<!-- <env name="DB_DATABASE" value=":memory:"/> -->', '<env name="DB_DATABASE" value="testing"/>', $phpunit);

        file_put_contents($this->laravel->basePath('phpunit.xml'), $phpunit);
    }

    /**
     * Prepare the installation by pulling and building any necessary images.
     *
     * @param array $services
     * @return void
     */
    protected function prepareInstallation($services)
    {
        // Ensure docker is installed...
        if ($this->runCommands(['docker info > /dev/null 2>&1']) !== 0) {
            return;
        }

        if (count($services) > 0) {
            $this->runCommands([
                './vendor/bin/nge pull ' . implode(' ', $services),
            ]);
        }

        $this->runCommands([
            './vendor/bin/nge build',
        ]);
    }

    /**
     * Run the given commands.
     *
     * @param array $commands
     * @return int
     */
    protected function runCommands($commands)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) {
            $this->output->write('    ' . $line);
        });
    }
}
