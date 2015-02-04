<?php

namespace Stevebauman\Translation;

use Stevebauman\Translation\Models\Locale as LocaleModel;
use Stevebauman\Translation\Models\Translation as TranslationModel;
use Illuminate\Cache\CacheManager as Cache;
use Illuminate\Session\SessionManager as Session;
use Illuminate\Config\Repository as Config;

/**
 * Class Translation
 * @package Stevebauman\Translation
 */
class Translation {

    /**
     * Holds the default application locale
     *
     * @var string
     */
    protected $defaultLocale = '';

    /**
     * Holds the locale model
     *
     * @var LocaleModel
     */
    protected $localeModel;

    /**
     * Holds the translation model
     *
     * @var TranslationModel
     */
    protected $translationModel;

    /**
     * Holds the current cache instance
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Holds the current session instance
     *
     * @var Session
     */
    protected $session;

    /**
     * Holds the current config instance
     *
     * @var Config
     */
    protected $config;

    /**
     * @param Config $config
     * @param Session $session
     * @param Cache $cache
     * @param LocaleModel $localeModel
     * @param TranslationModel $translationModel
     */
    public function __construct(
        Config $config,
        Session $session,
        Cache $cache,
        LocaleModel $localeModel,
        TranslationModel $translationModel)
    {
        $this->config = $config;
        $this->session = $session;
        $this->cache = $cache;

        $this->localeModel = $localeModel;
        $this->translationModel = $translationModel;

        $this->setDefaultLocale($this->getAppLocale());
    }

    /**
     * Returns the translation for the current locale
     *
     * @param string $text
     */
    public function translate($text = '')
    {
        $defaultTranslation = $this->getDefaultTranslation($text);

        /**
         * If a default translation record exists for inputted text, we'll try to find the
         * translation by the current locale ID and return the text. If there is no translation for the text
         * we'll create a new translation record for the session locale and insert the default
         * translation text
         */
        if($defaultTranslation)
        {
            $toLocale = $this->firstOrCreateLocale($this->getLocale());

            $translation = $this->findTranslationByLocaleIdAndParentId($toLocale->id, $defaultTranslation->id);

            if($translation)
            {

                return $translation->translation;

            } else {

                /*
                 * If the default translation locale doesn't equal the locale to translate to,
                 * we'll create a new translation record with the default
                 * translation text and return the default translation text
                 */
                if($defaultTranslation->locale_id != $toLocale->id) {

                    $this->createTranslation($toLocale->id, $defaultTranslation->translation, $defaultTranslation->id);

                }

                return $defaultTranslation->translation;
            }

        } else {

            /*
             * Default translation doesn't exist, lets get the default locale record
             * and create the translation, then run it through again so it creates
             * the translation record for the current locale in the session
             */
            $defaultLocale = $this->firstOrCreateLocale($this->getDefaultLocale());

            $translation = $this->createTranslation($defaultLocale->id, $text);

            return $this->translate($translation->translation);

        }

    }

    /**
     * Retrieves the current app's default locale
     *
     * @return mixed
     */
    public function getAppLocale()
    {
        return $this->config->get('app.locale');
    }

    /**
     * Retrieves the default locale property
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * Retrieves the current locale from the session. If a locale isn't set then the default app locale
     * is set as the current locale
     *
     * @return string
     */
    public function getLocale()
    {
        $locale = $this->session->get('locale');

        if($locale)
        {
            return $locale;
        } else {

            /*
             * First session
             */
            $this->setLocale($this->getDefaultLocale());

            return $this->getLocale();
        }
    }

    /**
     * Sets the default locale property
     *
     * @param string $code
     */
    public function setDefaultLocale($code = '')
    {
        $this->defaultLocale = $code;
    }

    /**
     * Sets the current locale in the session
     *
     * @param string $code
     */
    public function setLocale($code = '')
    {
        $this->session->set('locale', $code);
    }

    /**
     * Returns the translation by the specified text and the applications
     * default locale
     *
     * @param $text
     * @return mixed
     */
    public function getDefaultTranslation($text)
    {
        $locale = $this->findLocaleByCode($this->getDefaultLocale());

        return $this->translationModel
            ->where('locale_id', $locale->id)
            ->where('translation', $text)->first();
    }

    /**
     * Returns a locale by the specified code
     *
     * @param string $code
     * @return mixed
     */
    private function findLocaleByCode($code = '')
    {
        return $this->localeModel->where('code', $code)->first();
    }

    /**
     * Retrieves or creates a locale from the specified code
     *
     * @param $code
     * @return static
     */
    private function firstOrCreateLocale($code)
    {
        return $this->localeModel->firstOrCreate(array(
            'code' => $code,
        ));
    }

    /**
     * Returns the translation from the parent records
     *
     * @param $localeId
     * @param $parentId
     * @return mixed
     */
    private function findTranslationByLocaleIdAndParentId($localeId, $parentId)
    {
        return $this->translationModel
            ->where('locale_id', $localeId)
            ->where('translation_id', $parentId)->first();
    }

    /**
     * Returns the translation record by the specified text
     *
     * @param string $text
     * @return mixed
     */
    private function findTranslationByText($text = '')
    {
        return $this->translationModel
            ->where('translation', $text)->first();
    }

    /**
     * Creates a translation
     *
     * @param $localeId
     * @param $text
     * @param null $parentId
     * @return static
     */
    private function createTranslation($localeId, $text, $parentId = NULL)
    {
        $translation = $this->translationModel->firstOrCreate(array(
            'locale_id' => $localeId,
            'translation_id' => $parentId,
            'translation' => $text,
        ));

        return $translation;
    }

}