<?php

namespace Stevebauman\Translation;

use ErrorException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Stevebauman\Translation\Contracts\Client as ClientInterface;
use Stevebauman\Translation\Contracts\Translation as TranslationInterface;
use UnexpectedValueException;

class Translation implements TranslationInterface
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
     * Holds the translation client.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * Holds the current cache instance.
     *
     * @var \Illuminate\Cache\CacheManager
     */
    protected $cache;

    /**
     * Holds the current config instance.
     *
     * @var \Illuminate\Config\Repository
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
     * {@inheritdoc}
     */
    public function __construct(Application $app)
    {
        $this->config = $app->make('config');
        $this->cache = $app->make('cache');
        $this->request = $app->make('request');

        $this->localeModel = $app->make($this->getConfigLocaleModel());
        $this->translationModel = $app->make($this->getConfigTranslationModel());
        $this->client = $app->make($this->getConfigClient());

        // Set the default locale to the current application locale
        $this->setLocale($this->getConfigDefaultLocale());

        // Set the cache time from the configuration
        $this->setCacheTime($this->getConfigCacheTime());
    }

    /**
     * {@inheritdoc}
     */
    public function translate($text = '', $replacements = [], $toLocale = '', $runOnce = false)
    {
        try {
            // Make sure $text is actually a string and not and object / int
            $this->validateText($text);

            // Get the default translation text. This will insert the translation
            // and the default application locale if they don't
            // exist using firstOrCreate
            $defaultTranslation = $this->getDefaultTranslation($text);

            // If there are replacements inside the array we need to convert them
            // into google translate safe placeholders. ex :name to __name__
            if (count($replacements) > 0) {
                $defaultTranslation->translation = $this->makeTranslationSafePlaceholders($text, $replacements);
            }

            // If a toLocale has been provided, we're only translating a single string, so
            // we won't call the getLocale method as it retrieves and sets the default
            // session locale. If it has not been provided, we'll get the
            // default locale, and set it on the current session.
            if ($toLocale) {
                $toLocale = $this->firstOrCreateLocale($toLocale);
            } else {
                $toLocale = $this->firstOrCreateLocale($this->getLocale());
            }

            // Check if translation is requested for default locale.
            // If it is default locale we can just return default translation.
            if ($defaultTranslation->getAttribute($this->localeModel->getForeignKey()) == $toLocale->getKey()) {
                return $this->makeReplacements($defaultTranslation->translation, $replacements);
            }

            // Since we are not on default translation locale, we will have to
            // create (or get first) translation for provided locale where
            // parent translation is our default translation.
            $translation = $this->firstOrCreateTranslation(
                $toLocale,
                $defaultTranslation->translation,
                $defaultTranslation
            );

            return $this->makeReplacements($translation->translation, $replacements);
        } catch (\Illuminate\Database\QueryException $e) {
            // If foreign key integrity constrains fail, we have a caching issue
            if (!$runOnce) {
                // If this has not been run before, proceed

                // Burst locale cache
                $this->removeCacheLocale($toLocale->code);

                // Burst translation cache
                $this->removeCacheTranslation($this->translationModel->firstOrNew([
                        $toLocale->getForeignKey() => $toLocale->getKey(),
                        'translation'              => $text,
                    ])
                );

                // Attempt translation 1 more time
                return $this->translate($text, $replacements, $toLocale->code, $runOnce = true);
            } else {
                // If it has already tried translating once and failed again,
                // prevent infinite loops and just return the text
                return $text;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAppLocale()
    {
        return $this->config->get('app.locale');
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutePrefix()
    {
        $locale = $this->request->segment($this->getConfigRequestSegment());

        $locales = $this->getConfigLocales();

        if (is_array($locales) && in_array($locale, array_keys($locales))) {
            return $locale;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        if ($this->request->hasCookie('locale')) {
            return $this->request->cookie('locale');
        } else {
            return $this->getConfigDefaultLocale();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($code = '')
    {
        $this->locale = $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTranslation($text)
    {
        $locale = $this->firstOrCreateLocale($this->getConfigDefaultLocale());

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
    protected function makeTranslationSafePlaceholders($text, array $replace = [])
    {
        if (count($replace) > 0) {
            foreach ($replace as $key => $value) {
                // Search for :key
                $search = ':'.$key;

                // Replace it with __key__
                $replace = $this->makeTranslationSafePlaceholder($key);

                // Perform the replacements
                $text = str_replace($search, $replace, $text);
            }
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
    protected function makeTranslationSafePlaceholder($key = '')
    {
        return '___'.strtolower($key).'___';
    }

    /**
     * Make the place-holder replacements on the specified text.
     *
     * @param string $text
     * @param array  $replacements
     *
     * @return string
     */
    protected function makeReplacements($text, array $replacements)
    {
        if (count($replacements) > 0) {
            foreach ($replacements as $key => $value) {
                $replace = $this->makeTranslationSafePlaceholder($key);

                $text = str_ireplace($replace, $value, $text);
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
    protected function firstOrCreateLocale($code)
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
     * Creates a translation.
     *
     * @param Model  $locale
     * @param string $text
     * @param Model  $parentTranslation
     *
     * @return Model
     */
    protected function firstOrCreateTranslation(Model $locale, $text, $parentTranslation = null)
    {
        // We'll check to see if there's a cached translation
        // first before we try and hit the database.
        $cachedTranslation = $this->getCacheTranslation($locale, $text);

        if ($cachedTranslation instanceof Model) {
            return $cachedTranslation;
        }

        // Check if auto translation is enabled. If so we'll run
        // the text through google translate and
        // save it, then cache it.
        if ($parentTranslation && $this->autoTranslateEnabled()) {
            $this->client->setSource($parentTranslation->locale->code);
            $this->client->setTarget($locale->code);

            try {
                $text = $this->client->translate($text);
            } catch (ErrorException $e) {
                // Request to translate failed, set the text
                // to the parent translation.
                $text = $parentTranslation->translation;
            } catch (UnexpectedValueException $e) {
                // Looks like something other than text was passed in,
                // we'll set the text to the parent translation
                // for this exception as well.
                $text = $parentTranslation->translation;
            }
        }

        if ($parentTranslation) {
            // If a parent translation is given we're looking for it's child translation.
            $translation = $this->translationModel->firstOrNew([
                $locale->getForeignKey()                 => $locale->getKey(),
                $this->translationModel->getForeignKey() => $parentTranslation->getKey(),
            ]);
        } else {
            // Otherwise we're creating the parent translation.
            $translation = $this->translationModel->firstOrNew([
                $locale->getForeignKey() => $locale->getKey(),
                'translation'            => $text,
            ]);
        }

        if (empty($translation->getAttribute('translation'))) {
            // We need to make sure we don't overwrite the translation
            // if it exists already in case it was modified.
            $translation->setAttribute('translation', $text);
        }

        if ($translation->isDirty()) {
            $translation->save();
        }

        // Cache the translation so it's retrieved faster next time
        $this->setCacheTranslation($translation);

        return $translation;
    }

    /**
     * Sets a cache key to the specified locale and text.
     *
     * @param Model $translation
     */
    protected function setCacheTranslation(Model $translation)
    {
        if ($translation->parent instanceof Model) {
            $id = $this->getTranslationCacheId($translation->locale, $translation->parent->translation);
        } else {
            $id = $this->getTranslationCacheId($translation->locale, $translation->translation);
        }

        if (!$this->cache->has($id)) {
            $this->cache->put($id, $translation, $this->cacheTime);
        }
    }

    /**
     * Remove the translation from the cache manually.
     *
     * @param Model $translation
     */
    protected function removeCacheTranslation(Model $translation)
    {
        $id = $this->getTranslationCacheId($translation->locale, $translation->translation);

        if ($this->cache->has($id)) {
            $this->cache->forget($id);
        }
    }

    /**
     * Retrieves the cached translation from the specified locale
     * and text.
     *
     * @param Model  $locale
     * @param string $text
     *
     * @return bool|Model
     */
    protected function getCacheTranslation(Model $locale, $text)
    {
        $id = $this->getTranslationCacheId($locale, $text);

        $cachedTranslation = $this->cache->get($id);

        if ($cachedTranslation instanceof Model) {
            return $cachedTranslation;
        }

        // Cached translation wasn't found, let's return
        // false so we know to generate one.
        return false;
    }

    /**
     * Sets a cache key to the specified locale.
     *
     * @param Model $locale
     */
    protected function setCacheLocale(Model $locale)
    {
        if (!$this->cache->has($locale->code)) {
            $id = sprintf('translation.%s', $locale->code);

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
    protected function getCacheLocale($code)
    {
        $id = sprintf('translation.%s', $code);

        if ($this->cache->has($id)) {
            return $this->cache->get($id);
        }

        return false;
    }

    /**
     * Remove a locale from the cache.
     *
     * @param string $code
     */
    protected function removeCacheLocale($code)
    {
        $id = sprintf('translation.%s', $code);

        if ($this->cache->has($id)) {
            $this->cache->forget($id);
        }
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
    protected function getTranslationCacheId(Model $locale, $text)
    {
        $compressed = $this->compressString($text);

        return sprintf('translation.%s.%s', $locale->code, $compressed);
    }

    /**
     * Returns a the english name of the locale code entered from the config file.
     *
     * @param string $code
     *
     * @return string
     */
    protected function getConfigLocaleByCode($code)
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
    protected function setCacheTime($time)
    {
        if (is_numeric($time)) {
            $this->cacheTime = $time;
        }
    }

    /**
     * Returns the default locale from the configuration.
     *
     * @return string
     */
    protected function getConfigDefaultLocale()
    {
        return $this->config->get('translation.default_locale', 'en');
    }

    /**
     * Returns the locale model from the configuration.
     *
     * @return string
     */
    protected function getConfigLocaleModel()
    {
        return $this->config->get('translation.models.locale', Models\Locale::class);
    }

    /**
     * Returns the translation model from the configuration.
     *
     * @return string
     */
    protected function getConfigTranslationModel()
    {
        return $this->config->get('translation.models.translation', Models\Translation::class);
    }

    /**
     * Returns the translation client from the configuration.
     *
     * @return string
     */
    protected function getConfigClient()
    {
        return $this->config->get('translation.clients.client', Clients\GoogleTranslate::class);
    }

    /**
     * Returns the request segment to retrieve the locale from.
     *
     * @return int
     */
    protected function getConfigRequestSegment()
    {
        return $this->config->get('translation.request_segment', 1);
    }

    /**
     * Returns the array of configuration locales.
     *
     * @return array
     */
    protected function getConfigLocales()
    {
        return $this->config->get('translation.locales');
    }

    /**
     * Returns the cache time set from the configuration file.
     *
     * @return string|int
     */
    protected function getConfigCacheTime()
    {
        return $this->config->get('translation.cache_time', $this->cacheTime);
    }

    /**
     * Returns the auto translate configuration option.
     *
     * @return bool
     */
    protected function autoTranslateEnabled()
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
    protected function compressString($string)
    {
        return md5($string);
    }

    /**
     * Validates the inserted text to make sure it's a string.
     *
     * @param $text
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    protected function validateText($text)
    {
        if (!is_string($text)) {
            $message = 'Invalid Argument. You must supply a string to be translated.';

            throw new InvalidArgumentException($message);
        }

        return true;
    }
}
