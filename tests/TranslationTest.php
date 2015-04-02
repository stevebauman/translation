<?php

namespace Stevebauman\Translation\Tests;

use Mockery as m;
use Stevebauman\Translation\Models\Locale as LocaleModel;
use Stevebauman\Translation\Models\LocaleTranslation as TranslationModel;
use Stevebauman\Translation\Translation;

class TranslationTest extends FunctionalTestCase
{
    protected $translation;

    protected $mockedApp;

    protected $mockedConfig;

    protected $mockedSession;

    protected $mockedCache;

    public function setUp()
    {
        parent::setUp();

        $this->setMocks();

        $this->mockedConfig->shouldReceive('get')->andReturnValues(array(
            'en',
            30,
            array(
                'en' => 'English',
                'fr' => 'French',
                'ru' => 'Russian',
            ),
        ));

        $this->translation = new Translation(
            $this->mockedApp,
            $this->mockedConfig,
            $this->mockedSession,
            $this->mockedCache,
            new LocaleModel,
            new TranslationModel
        );
    }

    private function setMocks()
    {
        $this->mockedApp = m::mock('Illuminate\Foundation\Application');
        $this->mockedConfig = m::mock('Illuminate\Config\Repository');
        $this->mockedSession = m::mock('Illuminate\Session\SessionManager');
        $this->mockedCache = m::mock('Illuminate\Cache\CacheManager');
    }

    private function prepareMockedCacheForTranslate()
    {
        $this->mockedCache
            ->shouldReceive('get')->once()->andReturn(false)
            ->shouldReceive('has')->once()->andReturn(false)
            ->shouldReceive('put')->once()->andReturn(false);
    }


    private function prepareMockedSessionForTranslate($locale = 'en')
    {
        $this->mockedSession
            ->shouldReceive('get')->once()->andReturn(false)
            ->shouldReceive('set')->once()->andReturn(true)
            ->shouldReceive('get')->once()->andReturn($locale);
    }

    private function prepareMockedAppForTranslate()
    {
        $this->mockedApp->shouldReceive('setLocale')->once()->andReturn(true);
    }

    public function testDefaultTranslate()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate();

        $this->prepareMockedAppForTranslate();

        $result = $this->translation->translate('test');

        $this->assertEquals('test', $result);
        $this->assertEquals('en', $this->translation->getDefaultLocale());

        $locale = LocaleModel::first();

        $this->assertEquals('en', $locale->code);

        $translation = TranslationModel::first();

        $this->assertEquals(1, $translation->locale_id);
        $this->assertEquals('test', $translation->translation);
    }

    public function testSetLocale()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $this->translation->setLocale('fr');

        $this->assertEquals('fr', $this->translation->getLocale());
    }

    public function testTranslateToFrench()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $result = $this->translation->translate('Home');

        $this->assertEquals('Maison', $result);

        $locales = LocaleModel::get();

        $this->assertEquals('fr', $locales->get(1)->code);
        $this->assertEquals('French', $locales->get(1)->name);

        $translations = TranslationModel::get();

        $english = $translations->get(0);
        $this->assertEquals(1, $english->locale_id);
        $this->assertEquals('Home', $english->translation);

        $french = $translations->get(1);
        $this->assertEquals(2, $french->locale_id);
        $this->assertEquals(1, $french->translation_id);
        $this->assertEquals('Maison', $french->translation);
    }

    public function testTranslatePlaceholders()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $result = $this->translation->translate('Hello :first_name :last_name, welcome to our website.', array(
            'first_name' => 'John',
            'last_name' => 'Doe'));

        $this->assertEquals('Bonjour John Doe , bienvenue sur notre site .', $result);
    }

    public function testTranslateOnlyPlaceholder()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $result = $this->translation->translate(':test', array('test' => 'test'));

        $this->assertEquals('test', $result);
    }

    public function testTranslateMultipleSamePlaceholders()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $result = $this->translation->translate(':name :name :name', array('name' => 'John'));

        $this->assertEquals('John John John', $result);
    }

    public function testTranslatePlaceholdersWithWrongData()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $result = $this->translation->translate(':site_title :site_name', array('title' => 'Website', 'site_name' => 'test'));

        $this->assertEquals(': site_title test', $result);
    }

    public function testTranslateInvalidLocaleCode()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('testing');

        $this->prepareMockedAppForTranslate();

        $this->setExpectedException('Stevebauman\Translation\Exceptions\InvalidLocaleCodeException');

        $this->translation->translate('test');
    }

    public function testTranslateInvalidArgumentException()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate();

        $this->prepareMockedAppForTranslate();

        $this->setExpectedException('InvalidArgumentException');

        $this->translation->translate(new TranslationModel);
    }

    public function testTranslateIsParent()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate();

        $this->prepareMockedAppForTranslate();

        $this->translation->translate('test');

        $translation = TranslationModel::find(1);

        $this->assertTrue($translation->isParent());
    }

    public function testTranslateIsNotParent()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $this->translation->translate('test');

        $translation = TranslationModel::find(2);

        $this->assertFalse($translation->isParent());
    }

    public function testTranslateGetTranslationsWithParent()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $this->translation->translate('test');

        $translation = TranslationModel::find(1);

        $translations = $translation->getTranslations();

        $this->assertEquals(1, $translations->count());
        $this->assertEquals('fr', $translations->get(0)->locale->code);
    }

    public function testTranslateGetTranslationsWithoutParent()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $this->translation->translate('test');

        $translation = TranslationModel::find(2);

        $translations = $translation->getTranslations();

        $this->assertFalse($translations);
    }

    public function testTranslateGetParentTranslations()
    {
        $this->prepareMockedCacheForTranslate();

        $this->prepareMockedSessionForTranslate('fr');

        $this->prepareMockedAppForTranslate();

        $this->translation->translate('test');

        $translation = TranslationModel::find(2);

        $translations = $translation->parent->getTranslations();

        $this->assertEquals(1, $translations->count());
        $this->assertEquals('fr', $translations->get(0)->locale->code);
    }
}