<?php

namespace Stevebauman\Translation;

use InvalidArgumentException;
use Stichoza\GoogleTranslate\TranslateClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;

class Translation
{
    /**
     * Holds the application locale.
     *
     * @var string
     */
    protected $locale = '';

    /**
     * Holds the locale model.
     *
     * @var Model
     */
    protected $localeModel;

    /**
     * Holds the translation model.
     *
     * @var Model
     */
    protected $translationModel;

    /**
     * Holds the current application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * Holds the current cache instance.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $cache;

    /**
     * Holds the current config instance.
     *
     * @var \Illuminate\Cache\CacheManager
     */
    protected $config;

    /**
     * Holds the current request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The default amount of time (minutes) to store the cached translations.
     *
     * @var int
     */
    private $cacheTime = 30;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app->make('config');
        $this->cache = $app->make('cache');
        $this->request = $app->make('request');

        $this->localeModel = $app->make($this->getConfigLocaleModel());
        $this->translationModel = $app->make($this->getConfigTranslationModel());

        // Set the default locale to the current application locale
        $this->setLocale($this->getAppLocale());

        // Set the cache time from the configuration
        $this->setCacheTime($this->getConfigCacheTime());
    }

    /**
     * Returns the translation for the current locale.
     *
     * @param string $text
     * @param array  $replacements
     * @param string $toLocale
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function translate($text = '', $replacements = [], $toLocale = '')
    {
        // Make sure $text is actually a string and not and object / int
        $this->validateText($text);

        /*
         * If there are replacements inside the array we need to convert them
         * into google translate safe placeholders. ex :name to __name__
         */
        if (count($replacements) > 0) {
            $text = $this->makeTranslationSafePlaceholders($text, $replacements);
        }

        /*
         * Get the default translation text. This will insert
         * the translation and the default application locale
         * if they don't exist using firstOrCreate
         */
        $defaultTranslation = $this->getDefaultTranslation($text);

        /*
         * If a toLocale has been provided, we're only translating
         * a single string, so we won't call the getLocale method
         * as it retrieves and sets the default session locale.
         * If it has not been provided, we'll get the default
         * locale, and set it on the current session.
         */
        if ($toLocale) {
            $toLocale = $this->firstOrCreateLocale($toLocale);
        } else {
            $toLocale = $this->firstOrCreateLocale($this->getLocale());
        }

        // Find the translation by the 'toLocale' and it's parent default translation ID
        $translation = $this->findTranslationByLocaleIdAndParentId($toLocale->getKey(), $defaultTranslation->getKey());

        if ($translation) {
            /*
             * A translation was found, we'll return it,
             * but we need to make the final placeholder
             * replacements if they exist
             */
            return $this->makeReplacements($translation->translation, $replacements);
        } else {
            /*
             * If the default translation locale doesn't equal the locale to translate to,
             * we'll create a new translation record with the default
             * translation text, translate it, and return the translated text
             */
            if ($defaultTranslation->getAttribute('locale_id') != $toLocale->getKey()) {
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
     * Retrieves the current app's default locale.
     *
     * @return string
     */
    public function getAppLocale()
    {
        return $this->config->get('app.locale');
    }

    /**
     * Returns a route prefix to automatically set a locale depending on the segment
     *
     * @return null|string
     */
    public function getRoutePrefix()
    {
        $locale = $this->request->segment($this->getConfigRequestSegment());

        $locales = $this->getConfigLocales();

        if(in_array($locale, array_keys($locales))) {
            return $locale;
        }

        return null;
    }

    /**
     * Retrieves the current locale from the session. If a locale
     * isn't set then the default app locale is set as the current locale.
     *
     * @return string
     */
    public function getLocale()
    {
        if($this->request->hasCookie('locale')) {
            return $this->request->cookie('locale');
        } else {
            return $this->getAppLocale();
        }
    }

    /**
     * Sets the default locale property.
     *
     * @param string $code
     */
    public function setLocale($code = '')
    {
        $this->locale = $code;
    }

    /**
     * Returns the translation by the specified
     * text and the applications default locale.
     *
     * @param string $text
     *
     * @return Model
     */
    public function getDefaultTranslation($text)
    {
        $locale = $this->firstOrCreateLocale($this->getAppLocale());

        return $this->firstOrCreateTranslation($locale, $text);
    }

    /**
     * Replaces laravel translation placeholders with google
     * translate safe placeholders. Ex:.
     *
     * Converts:
     *      :name
     *
     * Into:
     *      __name__
     *
     * @param string $text
     * @param array  $replace
     *
     * @return mixed
     */
    private function makeTranslationSafePlaceholders($text, array $replace = [])
    {
        foreach ($replace as $key => $value) {
            // Search for :key
            $search = ':'.$key;

            // Replace it with __key__
            $replace = $this->makeTranslationSafePlaceholder($key);

            // Perform the replacements
            $text = str_replace($search, $replace, $text);
        }

        return $text;
    }

    /**
     * Makes a placeholder by the specified key.
     *
     * @param string $key
     *
     * @return string
     */
    private function makeTranslationSafePlaceholder($key = '')
    {
        return '__'.$key.'__';
    }

    /**
     * Make the place-holder replacements on the specified text.
     *
     * @param string $text
     * @param array  $replacements
     *
     * @return string
     */
    private function makeReplacements($text, array $replacements)
    {
        if (count($replacements) > 0) {
            foreach ($replacements as $key => $value) {
                $replace = $this->makeTranslationSafePlaceholder($key);

                $text = str_replace($replace, $value, $text);
            }
        }

        return $text;
    }

    /**
     * Retrieves or creates a locale from the specified code.
     *
     * @param string $code
     *
     * @return Model
     */
    private function firstOrCreateLocale($code)
    {
        $cachedLocale = $this->getCacheLocale($code);

        if ($cachedLocale) {
            return $cachedLocale;
        }

        $name = $this->getConfigLocaleByCode($code);

        $locale = $this->localeModel->firstOrCreate([
            'code' => $code,
            'name' => $name,
        ]);

        $this->setCacheLocale($locale);

        return $locale;
    }

    /**
     * Returns the translation from the parent records.
     *
     * @param int $localeId
     * @param int $parentId
     *
     * @return Model|null
     */
    private function findTranslationByLocaleIdAndParentId($localeId, $parentId)
    {
        return $this->translationModel
            ->where('locale_id', $localeId)
            ->where('translation_id', $parentId)
            ->first();
    }

    /**
     * Creates a translation.
     *
     * @param Model  $locale
     * @param string $text
     * @param Model  $parentTranslation
     *
     * @return Model
     */
    private function firstOrCreateTranslation(Model $locale, $text, $parentTranslation = null)
    {
        /*
         * We'll check to see if there's a cached translation
         * first before we try and hit the database
         */
        $cachedTranslation = $this->getCacheTranslation($locale, $text);

        if ($cachedTranslation) {
            return $cachedTranslation;
        }

        /*
         * Check if auto translation is enabled. If so we'll run
         * the text through google translate and save it, then cache it.
         */
        if ($parentTranslation && $this->autoTranslateEnabled()) {
            $googleTranslate = new TranslateClient();

            $googleTranslate->setSource($parentTranslation->locale->code);
            $googleTranslate->setTarget($locale->code);

            try {
                $text = $googleTranslate->translate($text);
            } catch (\ErrorException $e) {
                /*
                 * Request to translate failed, set
                 * the text to the parent translation
                 */
                $text = $parentTranslation->translation;
            } catch (\UnexpectedValueException $e) {
                /*
                 * Looks like something other than text was
                 * passed in, we'll set the text to the parent
                 * translation for this exception as well
                 */
                $text = $parentTranslation->translation;
            }
        }

        $translation = $this->translationModel->firstOrCreate([
            'locale_id' => $locale->getKey(),
            'translation_id' => (isset($parentTranslation) ? $parentTranslation->getKey()  : null),
            'translation' => $text,
        ]);

        // Cache the translation so it's retrieved faster next time
        $this->setCacheTranslation($translation);

        return $translation;
    }

    /**
     * Sets a cache key to the specified locale and text.
     *
     * @param Model $translation
     */
    private function setCacheTranslation(Model $translation)
    {
        $id = $this->getTranslationCacheId($translation->locale, $translation->translation);

        if (!$this->cache->has($id)) {
            $this->cache->put($id, $translation, $this->cacheTime);
        }
    }

    /**
     * Retrieves the cached translation from the specified locale
     * and text.
     *
     * @param Model $locale
     * @param string $text
     *
     * @return bool|string
     */
    private function getCacheTranslation(Model $locale, $text)
    {
        $id = $this->getTranslationCacheId($locale, $text);

        $cachedTranslation = $this->cache->get($id);

        if ($cachedTranslation) {
            return $cachedTranslation;
        }

        /*
         * Cached translation wasn't found, let's
         * return false so we know to generate one
         */
        return false;
    }

    /**
     * Sets a cache key to the specified locale.
     *
     * @param Model $locale
     */
    private function setCacheLocale(Model $locale)
    {
        if (!$this->cache->has($locale->code)) {
            $id = sprintf('translation::%s', $locale->code);

            $this->cache->put($id, $locale, $this->cacheTime);
        }
    }

    /**
     * Retrieves a cached locale from the specified locale code.
     *
     * @param string $code
     *
     * @return bool
     */
    private function getCacheLocale($code)
    {
        $id = sprintf('translation::%s', $code);

        if($this->cache->has($id)) {
            return $this->cache->get($id);
        }

        return false;
    }

    /**
     * Returns a unique translation code by compressing the text
     * using a PHP compression function.
     *
     * @param Model  $locale
     * @param string $text
     *
     * @return string
     */
    private function getTranslationCacheId(Model $locale, $text)
    {
        $compressed = $this->compressString($text);

        return sprintf('translation::%s.%s', $locale->code, $compressed);
    }

    /**
     * Returns a the english name of the locale code entered from the config file.
     *
     * @param string $code
     *
     * @return string
     */
    private function getConfigLocaleByCode($code)
    {
        $locales = $this->getConfigLocales();

        if (is_array($locales) && array_key_exists($code, $locales)) {
            return $locales[$code];
        }

        return $code;
    }

    /**
     * Sets the time to store the translations and locales in cache.
     *
     * @param int $time
     */
    private function setCacheTime($time)
    {
        if (is_numeric($time)) {
            $this->cacheTime = $time;
        }
    }

    /**
     * Returns the locale model from the configuration.
     *
     * @return string
     */
    private function getConfigLocaleModel()
    {
        return $this->config->get('translation.models.locale', Models\Locale::class);
    }

    /**
     * Returns the translation model from the configuration.
     *
     * @return string
     */
    public function getConfigTranslationModel()
    {
        return $this->config->get('translation.models.translation', Models\LocaleTranslation::class);
    }

    /**
     * Returns the request segment to retrieve the locale from.
     *
     * @return int
     */
    public function getConfigRequestSegment()
    {
        return $this->config->get('translation.request_segment', 1);
    }

    /**
     * Returns the array of configuration locales.
     *
     * @return array
     */
    private function getConfigLocales()
    {
        return $this->config->get('translation.locales');
    }

    /**
     * Returns the cache time set from the configuration file.
     *
     * @return string|int
     */
    private function getConfigCacheTime()
    {
        return $this->config->get('translation.cache_time', $this->cacheTime);
    }

    /**
     * Returns the auto translate configuration option.
     *
     * @return bool
     */
    private function autoTranslateEnabled()
    {
        return $this->config->get('translation.auto_translate', true);
    }

    /**
     * Calculates the md5 hash of a string.
     *
     * Used for storing cache keys for translations.
     *
     * @param $string
     *
     * @return string
     */
    private function compressString($string)
    {
        return md5($string);
    }

    /**
     * Validates the inserted text to make sure it's a string.
     *
     * @param $text
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    private function validateText($text)
    {
        if (!is_string($text)) {
            $message = 'Invalid Argument. You must supply a string to be translated.';

            throw new InvalidArgumentException($message);
        }

        return true;
    }
}
