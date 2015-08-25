<?php

namespace Stevebauman\Translation\Tests;

use Orchestra\Testbench\TestCase;
use Stevebauman\Translation\Models\Locale as LocaleModel;
use Stevebauman\Translation\Models\Translation as TranslationModel;
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
            '--realpath' => realpath(__DIR__.'/../src/Migrations'),
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

    public function testTranslationInvalidText()
    {
        $this->setExpectedException('InvalidArgumentException');

        Translation::translate(['Invalid']);
    }

    public function testTranslationInvalidReplacements()
    {
        $this->setExpectedException('ErrorException');

        Translation::translate('Valid', 'Invalid');
    }

    public function testTranslationDefaultLocale()
    {
        $this->assertEquals('Test', Translation::translate('Test'));

        $locale = LocaleModel::first();

        $this->assertEquals('en', $locale->code);
        $this->assertEquals('English', $locale->name);

        $translation = TranslationModel::first();

        $this->assertEquals('Test', $translation->translation);
        $this->assertEquals($locale->getKey(), $translation->locale_id);
    }

    public function testTranslationPlaceHolders()
    {
        $this->assertEquals('Hi :name', Translation::translate('Hi :name'));
        $this->assertEquals('Hi John', Translation::translate('Hi :name', ['name' => 'John']));

        $translations = TranslationModel::get();

        $this->assertEquals('Hi :name', $translations->get(0)->translation);
        $this->assertEquals('Hi ___name___', $translations->get(1)->translation);
    }

    public function testTranslationPlaceHoldersDynamicLanguage()
    {
        $replace = ['name' => 'John'];

        $this->assertEquals('Hello John', Translation::translate('Hello :name', $replace, 'en'));
        $this->assertEquals('Bonjour John', Translation::translate('Hello :name', $replace, 'fr'));
    }

    public function testTranslationPlaceHoldersMultiple()
    {
        $replace = [
            'name' => 'John',
            'apples' => '10',
            'bananas' => '15',
            'grapes' => '20',
        ];

        $expected = 'Hello John, I see you have 10 apples, 15 bananas, and 20 grapes.';

        $translation = 'Hello :name, I see you have :apples apples, :bananas bananas, and :grapes grapes.';

        $this->assertEquals($expected, Translation::translate($translation, $replace));
    }

    public function testTranslationPlaceHoldersMultipleOfTheSame()
    {
        $replace = [
            'name' => 'Name',
        ];

        $expected = 'Name Name Name Name Name';

        $translation = ':name :name :name :name :name';

        $this->assertEquals($expected, Translation::translate($translation, $replace));
    }
}
