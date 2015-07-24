<?php

namespace Stevebauman\Translation;

use Blade;
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
     * Set up the blade directive.
     */
    public function boot()
    {
        Blade::directive('t', function($expression) {
            return "<?php echo App::make('translation')->translate{$expression}; ?>";
        });
    }

    /**
     * Register the service provider.
     *
     * @method void package(string $package, string $namespace, string $path)
     */
    public function register()
    {
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('translation.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/migrations/' => base_path('/database/migrations'),
        ], 'migrations');

        $this->app->bind('translation', function($app) {
            return new Translation($app);
        });

        /*
         * Bind the translation scan command for artisan
         */
        $this->app->bind('translation:scan', function ($app) {
            return new Commands\ScanCommand($app['translation']);
        });

        /*
         * Register the commands
         */
        $this->commands([
            'translation:scan',
        ]);

        /*
         * Include the helpers file for global `_t()` function
         */
        include __DIR__.'/helpers.php';
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['translation'];
    }
}
