<?php

namespace Stevebauman\Translation\Tests;

use Illuminate\Support\Facades\Cache;
use Stevebauman\Translation\Facades\Translation;
use Stevebauman\Translation\Models\Locale as LocaleModel;
use Stevebauman\Translation\Models\Translation as TranslationModel;

class TranslationTest extends FunctionalTestCase
{
    public function testTranslationInvalidText()
    {
        $this->setExpectedException('InvalidArgumentException');

        Translation::translate(['Invalid']);
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

        $this->assertCount(1, $translations);
        $this->assertEquals('Hi :name', $translations->get(0)->translation);
    }

    public function testTranslationNoDuplicateEntriesAfterModification()
    {
        $replace = ['name' => 'John'];

        Translation::translate('Hello John, how are you?', $replace, 'fr');

        $translations = TranslationModel::get();

        $this->assertCount(2, $translations);

        $french = $translations->get(1);

        $french->translation = 'Changed Translation';

        $french->save();

        Translation::translate('Hello John, how are you?', $replace, 'fr');

        $newTranslations = TranslationModel::get();

        $this->assertCount(2, $newTranslations);
        $this->assertEquals('Changed Translation', $newTranslations->get(1)->translation);
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
            'name'    => 'John',
            'apples'  => '10',
            'bananas' => '15',
            'grapes'  => '20',
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

    public function testTranslationPlaceHoldersCaseInsensitivity()
    {
        $replace = [
            'name' => 'John',
            'NAME' => 'Test',
        ];

        $translation = ':name :NAME';

        $expected = 'John John';

        $this->assertEquals($expected, Translation::translate($translation, $replace));
    }

    public function testTranslationCachingWithDefaultLocale()
    {
        $this->app['config']['app.locale'] = 'en';
        $this->app['config']['translation.auto_translate'] = false;

        $text = 'Hello there!';

        $this->assertCachingIsWorking($text, [], 'en');
    }

    public function testTranslationCachingWithCustomLocale()
    {
        $this->app['config']['app.locale'] = 'en';
        $this->app['config']['translation.auto_translate'] = false;

        $text = 'Hello there!';

        $this->assertCachingIsWorking($text, [], 'fr');
    }

    public function testTranslationIsResolvedFromContract()
    {
        $contract = 'Stevebauman\Translation\Contracts\Translation';

        $translation = $this->app->make($contract);

        $this->assertInstanceOf($contract, $translation);
        $this->assertInstanceOf('Stevebauman\Translation\Translation', $translation);
    }

    public function testTranslationsAreNotRecreatedAfterAutoTranslateAndCacheIsDisabled()
    {
        $text = 'Hello John, how are you?';

        Translation::translate($text, [], 'fr');

        Cache::flush();

        $this->app['config']['translation.auto_translate'] = false;

        Translation::translate($text, [], 'fr');

        $this->assertCount(2, TranslationModel::get());
    }

    private function assertCachingIsWorking($text, $replacements = [], $localeCode = 'en')
    {
        $hash = md5($text);

        // After translating this text, result should be cached
        // and next time we execute translate method with same
        // text, it should return value from cache instead of
        // touching the database.
        $this->assertEquals($text, Translation::translate($text, $replacements, $localeCode));

        $this->assertTrue(Cache::has("translation.{$localeCode}"));
        $this->assertTrue(Cache::has("translation.{$localeCode}.{$hash}"));

        // Delete all translation data from database.
        TranslationModel::query()->delete();
        LocaleModel::query()->delete();

        // Execute translation again
        $this->assertEquals($text, Translation::translate($text, $replacements, $localeCode));

        // Asserting that there are no inserted values after
        // translate method is executed means that db is not touched at all
        // and that translation and locale is cached properly.
        $this->assertEquals(0, TranslationModel::all()->count());
        $this->assertEquals(0, LocaleModel::all()->count());
    }
}
