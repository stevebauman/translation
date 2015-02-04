<?php namespace Stevebauman\Translation;

use Stevebauman\Translation\Models\Translation as TranslationModel;
use Stevebauman\Translation\Models\Locale as LocaleModel;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->package('stevebauman/translation');

		$this->app['translation'] = $this->app->share(function($app)
		{
			return new Translation($app['config'], $app['session'], new LocaleModel, new TranslationModel);
		});

		include __DIR__ .'/../../helpers.php';
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
