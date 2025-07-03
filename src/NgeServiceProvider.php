<?php

namespace Dentro\Nge;

use Illuminate\Support\ServiceProvider;
use Dentro\Nge\Console\AddCommand;
use Dentro\Nge\Console\InstallCommand;

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

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../bin/nge' => $this->app->basePath('nge'),
            ], ['nge', 'nge-bin']);
        }
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
