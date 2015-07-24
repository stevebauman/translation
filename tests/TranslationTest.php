<?php

namespace Stevebauman\Translation\Tests;

use Orchestra\Testbench\TestCase;
use Stevebauman\Translation\Models\Locale;
use Stevebauman\Translation\Models\LocaleTranslation;
use Stevebauman\Translation\Facades\Translation;
use Stevebauman\Translation\TranslationServiceProvider;

class TranslationTest extends TestCase
{
    /**
     * Set up the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--realpath' => realpath(__DIR__.'/../src/migrations'),
        ]);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
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
    }

    /**
     * Returns the package providers.
     *
     * @return array
     */
    protected function getPackageProviders()
    {
        return [TranslationServiceProvider::class];
    }

    /**
     * Returns the package aliases.
     *
     * @return array
     */
    protected function getPackageAliases()
    {
        return ['Translation' => \Stevebauman\Translation\Facades\Translation::class];
    }

    public function testTranslate()
    {
        $this->assertEquals('Test', Translation::translate('Test'));
    }
}
