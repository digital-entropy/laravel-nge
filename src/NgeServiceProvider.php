<?php

namespace Dentro\Nge;

use Illuminate\Support\ServiceProvider;
use Dentro\Nge\Console\AddCommand;
use Dentro\Nge\Console\InstallCommand;
use Dentro\Nge\Console\PublishCommand;

class NgeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                AddCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            InstallCommand::class,
            AddCommand::class,
        ];
    }
}
