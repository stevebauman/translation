<?php

namespace Stevebauman\Translation\Tests;

use Orchestra\Testbench\TestCase;
use Stevebauman\Translation\TranslationServiceProvider;

class FunctionalTestCase extends TestCase
{
    /**
     * Set up the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__."/../src/Migrations");
        
        $this->artisan('migrate');
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $app['config']->set('translation.locales', [
            'en' => 'English',
            'fr' => 'French',
        ]);

        $app['config']->set('translation.clients.api_key', 123456);
    }

    /**
     * Returns the package providers.
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [TranslationServiceProvider::class];
    }

    /**
     * Returns the package aliases.
     *
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return ['Translation' => \Stevebauman\Translation\Facades\Translation::class];
    }

    protected function loadMigrationsFrom($paths) {
        $paths = (is_array($paths)) ? $paths : [$paths];
        $this->app->afterResolving('migrator', function ($migrator) use ($paths) {
            foreach ((array) $paths as $path) {
                $migrator->path($path);
            }
        });
    }
}
