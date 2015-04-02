<?php

namespace Stevebauman\Translation;

use InvalidArgumentException;
use Stichoza\GoogleTranslate\TranslateClient;
use Stevebauman\Translation\Exceptions\InvalidLocaleCodeException;
use Stevebauman\Translation\Models\Locale as LocaleModel;
use Stevebauman\Translation\Models\LocaleTranslation as TranslationModel;
use Illuminate\Cache\CacheManager as Cache;
use Illuminate\Session\SessionManager as Session;
use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Application as App;

/**
 * Class Translation
 * @package Stevebauman\Translation
 */
class Translation
{
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
     * Holds the current application instance
     *
     * @var App
     */
    protected $app;

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
     * Holds the separator to access config values.
     * This is for Laravel 4 / 5 compatibility
     *
     * @var string
     */
    private $configSeparator = '::';

    /**
     * The sprintf format to retrieve a translation from the cache
     *
     * @var string
     */
    private $cacheTranslationStr = 'translation::%s.%s';

    /**
     * The sprintf format to retrieve a translation from the cache
     *
     * @var string
     */
    private $cacheLocaleStr = 'translation::%s';

    /**
     * The amount of time (minutes) to store the cached translations
     *
     * @var int
     */
    private $cacheTime = 30;

    /**
     * Constructor.
     *
     * @param App $app
     * @param Config $config
     * @param Session $session
     * @param Cache $cache
     * @param LocaleModel $localeModel
     * @param TranslationModel $translationModel
     */
    public function __construct(
        App $app,
        Config $config,
        Session $session,
        Cache $cache,
        LocaleModel $localeModel,
        TranslationModel $translationModel)
    {
        $this->app = $app;
        $this->config = $config;
        $this->session = $session;
        $this->cache = $cache;

        $this->localeModel = $localeModel;
        $this->translationModel = $translationModel;

        /*
         * Set the default locale to the current application locale
         */
        $this->setDefaultLocale($this->getAppLocale());

        /*
         * Set the cache time from the configuration
         */
        $this->setCacheTime($this->getConfigCacheTime());

        /*
         * Set the configuration separator for compatibility with
         * Laravel 4 / 5
         */
        $this->setConfigSeparator();
    }

    /**
     * Returns the translation for the current locale
     *
     * @param string $text
     * @param array $replacements
     * @return string
     * @throws InvalidArgumentException
     */
    public function translate($text = '', $replacements = array())
    {
        /*
         * Make sure $text is actually a string and not
         * and object / int
         */
        $this->validateText($text);

        /*
         * If there are replacements inside the array we need to convert them
         * into google translate safe placeholders. ex :name to __name__
         */
        if(count($replacements) > 0) $text = $this->makeTranslationSafePlaceholders($text, $replacements);

        /*
         * Get the default translation text. This will insert
         * the translation and the default application locale
         * if they don't exist using firstOrCreate
         */
        $defaultTranslation = $this->getDefaultTranslation($text);

        /*
         * We need to create the locale we're translating to
         * as well, if it's different than default
         */
        $toLocale = $this->firstOrCreateLocale($this->getLocale());

        /*
         * Find the translation by the 'toLocale' and
         * it's parent default translation ID
         */
        $translation = $this->findTranslationByLocaleIdAndParentId($toLocale->id, $defaultTranslation->id);

        if($translation)
        {
            /*
             * A translation was found, we'll return it,
             * but we need to make the final placeholder
             * replacements if they exist
             */
            return $this->makeReplacements($translation->translation, $replacements);
        } else
        {
            /*
             * If the default translation locale doesn't equal the locale to translate to,
             * we'll create a new translation record with the default
             * translation text, translate it, and return the translated text
             */
            if($defaultTranslation->locale_id != $toLocale->id)
            {
                $translation = $this->firstOrCreateTranslation($toLocale, $defaultTranslation->translation, $defaultTranslation);

                return $this->makeReplacements($translation->translation, $replacements);
            }

            /*
             * Looks like we're on our default application locale.
             * We'll return default locale translation
             */
            return $this->makeReplacements($defaultTranslation->translation, $replacements);
        }
    }

    /**
     * Retrieves the current app's default locale
     *
     * @return string
     */
    public function getAppLocale()
    {
        return $this->config->get('app.locale');
    }

    /**
     * Retrieves the default locale property
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * Retrieves the current locale from the session. If a locale
     * isn't set then the default app locale is set as the current locale
     *
     * @return string
     */
    public function getLocale()
    {
        $locale = $this->session->get('locale');

        if($locale) return $locale;

        /*
         * First session
         */
        $this->setLocale($this->getDefaultLocale());

        return $this->getLocale();
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
     * Sets the current locale in the session as well as the application
     *
     * @param string $code
     */
    public function setLocale($code = '')
    {
        $this->session->set('locale', $code);

        $this->app->setLocale($code);
    }

    /**
     * Returns the translation by the specified
     * text and the applications default locale
     *
     * @param string $text
     * @return Translation
     */
    public function getDefaultTranslation($text)
    {
        $locale = $this->firstOrCreateLocale($this->getDefaultLocale());

        return $this->firstOrCreateTranslation($locale, $text);
    }

    /**
     * Replaces laravel translation placeholders with google
     * translate safe placeholders. Ex:
     *
     * Converts:
     *      :name
     *
     * Into:
     *      __name__
     *
     * @param $text
     * @param array $replace
     * @return mixed
     */
    private function makeTranslationSafePlaceholders($text, array $replace)
    {
        foreach ($replace as $key => $value)
        {
            // Search for :key
            $search = ':' . $key;

            // Replace it with __key__
            $replace = '__' . $key . '__';

            $text = str_replace($search, $replace, $text);
        }

        return $text;
    }

    /**
     * Make the place-holder replacements on the specified text
     *
     * @param  string  $text
     * @param  array   $replace
     * @return string
     */
    private function makeReplacements($text, array $replace)
    {
        if(count($replace) > 0)
        {
            foreach ($replace as $key => $value)
            {
                $text = str_replace('__' . $key . '__', $value, $text);
            }
        }

        return $text;
    }

    /**
     * Retrieves or creates a locale from the specified code
     *
     * @param string $code
     * @return static
     */
    private function firstOrCreateLocale($code)
    {
        $cachedLocale = $this->getCacheLocale($code);

        if($cachedLocale) return $cachedLocale;

        $name = $this->getConfigLocaleByCode($code);

        $locale = $this->localeModel->firstOrCreate(array(
            'code' => $code,
            'name' => $name,
        ));

        $this->setCacheLocale($locale);

        return $locale;
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
     * Creates a translation
     *
     * @param Translation $locale
     * @param string $text
     * @param Translation $parentTranslation
     * @return static
     */
    private function firstOrCreateTranslation($locale, $text, $parentTranslation = NULL)
    {
        /*
         * We'll check to see if there's a cached translation
         * first before we try and hit the database
         */
        $cachedTranslation = $this->getCacheTranslation($locale, $text);

        if($cachedTranslation) return $cachedTranslation;

        /*
         * Check if auto translation is enabled. If so we'll run
         * the text through google translate and save it, then cache it.
         */
        if($parentTranslation && $this->autoTranslateEnabled())
        {
            $googleTranslate = new TranslateClient;

            $googleTranslate->setSource($parentTranslation->locale->code);
            $googleTranslate->setTarget($locale->code);

            try
            {
                $text = $googleTranslate->translate($text);
            } catch(\ErrorException $e)
            {
                /*
                 * Request to translate failed, set
                 * the text to the parent translation
                 */
                $text = $parentTranslation->translation;
            } catch(\UnexpectedValueException $e)
            {
                /*
                 * Looks like something other than text was
                 * passed in, we'll set the text to the parent
                 * translation for this exception as well
                 */
                $text = $parentTranslation->translation;
            }

            if($this->autoTranslateUcfirstEnabled()) $text = ucfirst($text);
        }

        $translation = $this->translationModel->firstOrCreate(array(
            'locale_id' => $locale->id,
            'translation_id' => (isset($parentTranslation) ? $parentTranslation->id  : NULL),
            'translation' => $text,
        ));

        /*
         * Cache the translation so it's retrieved faster next time
         */
        $this->setCacheTranslation($translation);

        return $translation;
    }

    /**
     * Sets a cache key to the specified locale and text
     *
     * @param $translation
     */
    private function setCacheTranslation($translation)
    {
        $id = $this->getTranslationCacheId($translation->locale, $translation->translation);

        if( ! $this->cache->has($id)) $this->cache->put($id, $translation, $this->cacheTime);
    }

    /**
     * Retrieves the cached translation from the specified locale
     * and text
     *
     * @param $locale
     * @param $text
     * @return bool|string
     */
    private function getCacheTranslation($locale, $text)
    {
        $id = $this->getTranslationCacheId($locale, $text);

        $cachedTranslation = $this->cache->get($id);

        if($cachedTranslation) return $cachedTranslation;

        /*
         * Cached translation wasn't found, let's
         * return false so we know to generate one
         */
        return false;
    }

    /**
     * Sets a cache key to the specified locale
     *
     * @param $locale
     * @return void
     */
    private function setCacheLocale($locale)
    {
        if(!$this->cache->has($locale->code))
        {
            $id = sprintf($this->cacheLocaleStr, $locale->code);

            $this->cache->put($id, $locale, $this->cacheTime);
        }
    }

    /**
     * Retrieves a cached locale from the specified locale code
     *
     * @param $code
     * @return bool
     */
    private function getCacheLocale($code)
    {
        $id = sprintf($this->cacheLocaleStr, $code);

        $cachedLocale = $this->cache->get($id);

        if($cachedLocale) return $cachedLocale;

        return false;
    }

    /**
     * Returns a unique translation code by compressing the text
     * using a PHP compression function
     *
     * @param $locale
     * @param $text
     * @return string
     */
    private function getTranslationCacheId($locale, $text)
    {
        $compressed = $this->compressString($text);

        return sprintf($this->cacheTranslationStr, $locale->code, $compressed);
    }

    /**
     * Returns a the english name of the locale code entered from the config file
     *
     * @param $code
     * @return mixed
     * @throws InvalidLocaleCodeException
     */
    private function getConfigLocaleByCode($code)
    {
        $locales = $this->getConfigLocales();

        if(is_array($locales) && array_key_exists($code, $locales)) return $locales[$code];

        $message = sprintf('Locale Code: %s is invalid, please make sure it is available in the configuration file', $code);

        throw new InvalidLocaleCodeException($message);
    }

    /**
     * Sets the time to store the translations and locales in cache
     *
     * @param $time
     * @return void
     */
    private function setCacheTime($time)
    {
        if(is_numeric($time)) $this->cacheTime = $time;
    }

    /**
     * Returns the array of configuration locales
     *
     * @return array
     */
    private function getConfigLocales()
    {
        return $this->config->get('translation'. $this->configSeparator .'locales');
    }

    /**
     * Returns the cache time set from the configuration file
     *
     * @return string|int
     */
    private function getConfigCacheTime()
    {
        return $this->config->get('translation:'. $this->configSeparator .'cache_time');
    }

    /**
     * Returns the auto translate configuration option
     *
     * @return bool
     */
    private function autoTranslateEnabled()
    {
        return $this->config->get('translation'. $this->configSeparator .'auto_translate');
    }

    /**
     * Returns the auto translate ucfirst configuration option
     *
     * @return bool
     */
    private function autoTranslateUcfirstEnabled()
    {
        return $this->config->get('translation'. $this->configSeparator .'auto_translate_ucfirst');
    }

    /**
     * Compresses a string. Used for storing cache keys for translations
     *
     * @param $string
     * @return string
     */
    private function compressString($string)
    {
        return gzcompress($string);
    }

    /**
     * Validates the inserted text to make sure it's a string
     *
     * @param $text
     * @return bool
     * @throws InvalidArgumentException
     */
    private function validateText($text)
    {
        if( ! is_string($text))
        {
            $message = "Invalid Argument. You must supply a string to be translated.";

            throw new InvalidArgumentException($message);
        }

        return true;
    }

    /**
     * Sets the configuration separator for Laravel 5 compatibility
     *
     * @return void
     */
    private function setConfigSeparator()
    {
        if(defined(get_class($this->app).'::VERSION'))
        {
            /*
             * Need to store app instance in new variable due to
             * constants being inaccessible via $this->app::VERSION
             */
            $app = $this->app;

            $appVersion = explode('.', $app::VERSION);

            if($appVersion[0] == 5) $this->configSeparator = '.';
        }
    }

}