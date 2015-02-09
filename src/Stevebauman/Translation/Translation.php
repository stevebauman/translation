<?php

namespace Stevebauman\Translation;

use Stichoza\Google\GoogleTranslate;
use Stevebauman\Translation\Exceptions\InvalidLocaleCode;
use Stevebauman\Translation\Models\Locale as LocaleModel;
use Stevebauman\Translation\Models\LocaleTranslation as TranslationModel;
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
     * @param array $data
     */
    public function translate($text = '', $data= array())
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

                    $translation = $this->createTranslation($toLocale, $defaultTranslation->translation, $defaultTranslation);

                    return $translation->translation;

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

            $translation = $this->createTranslation($defaultLocale, $text);

            return $translation->translation;

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
        $locale = $this->firstOrCreateLocale($this->getDefaultLocale());

        return $this->translationModel
            ->remember(1)
            ->where('locale_id', $locale->id)
            ->where('translation', $text)
            ->first();
    }

    /**
     * Returns a locale by the specified code
     *
     * @param string $code
     * @return mixed
     */
    private function findLocaleByCode($code = '')
    {
        return $this->localeModel
            ->remember(1)
            ->where('code', $code)
            ->first();
    }

    /**
     * Retrieves or creates a locale from the specified code
     *
     * @param $code
     * @return static
     */
    private function firstOrCreateLocale($code)
    {
        $name = $this->getConfigLocaleByCode($code);

        return $this->localeModel->firstOrCreate(array(
            'code' => $code,
            'name' => $name,
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
            ->remember(1)
            ->where('locale_id', $localeId)
            ->where('translation_id', $parentId)->first();
    }

    /**
     * Creates a translation
     *
     * @param $locale
     * @param $text
     * @param null $parentTranslation
     * @return static
     */
    private function createTranslation($locale, $text, $parentTranslation = NULL)
    {
        $parentId = NULL;

        /*
         * Check if auto translation is enabled, if so we'll run the text through google
         * translate and save the text.
         */
        if($parentTranslation && $this->autoTranslateEnabled())
        {
            $googleTranslate = new GoogleTranslate;

            $googleTranslate->setLangFrom($parentTranslation->locale->code);
            $googleTranslate->setLangTo($locale->code);

            $text = $googleTranslate->translate($text);

            if($this->autoTranslateUcfirstEnabled())
            {
                $text = ucfirst($text);
            }

            $parentId = $parentTranslation->id;
        }

        $translation = $this->translationModel->firstOrCreate(array(
            'locale_id' => $locale->id,
            'translation_id' => $parentId,
            'translation' => $text,
        ));



        return $translation;
    }

    /**
     * Returns a the english name of the locale code entered from the config file
     *
     * @param $code
     * @return mixed
     * @throws InvalidLocaleCode
     */
    private function getConfigLocaleByCode($code)
    {
        if(array_key_exists($code, $this->config->get('translation::locales')))
        {
            return $this->config->get('translation::locales')[$code];
        } else
        {
            $message = sprintf('Locale Code: %s is invalid, please make sure it is available in the configuration file', $code);

            throw new InvalidLocaleCode($message);
        }
    }

    /**
     * Returns the auto translate configuration option
     *
     * @return mixed
     */
    private function autoTranslateEnabled()
    {
        return $this->config->get('translation::auto_translate');
    }

    /**
     * Returns the auto translate ucfirst configuration option
     *
     * @return mixed
     */
    private function autoTranslateUcfirstEnabled()
    {
        return $this->config->get('translation::auto_translate_ucfirst');
    }

}