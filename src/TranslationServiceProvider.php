<?php namespace Stevebauman\Translation;

use Stevebauman\Translation\Models\LocaleTranslation as TranslationModel;
use Stevebauman\Translation\Models\Locale as LocaleModel;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @method void package(string $package, string $namespace, string $path)
	 * @return void
	 */
	public function register()
	{
		/*
		 * If the package method exists, we're using Laravel 4, if not then we're
		 * definitely on laravel 5
		 */
		if (method_exists($this, 'package'))
        {
			$this->package('stevebauman/translation');
		} else
        {
			$this->publishes([
				__DIR__ . '/config/config.php' => config_path('translation.php'),
			], 'config');

			$this->publishes([
				__DIR__ . '/migrations/' => base_path('/database/migrations'),
			], 'migrations');
		}

		/*
		 * Construct a new Translation instance and inject the application config, session, and cache.
		 *
		 * Also passing in new instances of the locale model and translation model for automatic record creation
		 */
		$this->app['translation'] = $this->app->share(function($app)
		{
			return new Translation($app, $app['config'], $app['session'], $app['cache'], new LocaleModel, new TranslationModel);
		});

		/*
		 * Bind the translation scan command for artisan
		 */
		$this->app->bind('translation:scan', function($app){
			return new Commands\ScanCommand($app['translation']);
		});

		/*
		 * Register the commands
		 */
		$this->commands(array(
			'translation:scan',
		));

		/*
		 * Include the helpers file for global `_t()` function
		 */
		include __DIR__ . '/helpers.php';
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('translation');
	}
}
