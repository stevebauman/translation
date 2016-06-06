# Translation

[![Travis CI](https://img.shields.io/travis/stevebauman/translation.svg?style=flat-square)](https://travis-ci.org/stevebauman/translation)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/stevebauman/translation.svg?style=flat-square)](https://scrutinizer-ci.com/g/stevebauman/translation/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/stevebauman/translation.svg?style=flat-square)](https://packagist.org/packages/stevebauman/translation)
[![Total Downloads](https://img.shields.io/packagist/dt/stevebauman/translation.svg?style=flat-square)](https://packagist.org/packages/stevebauman/translation)
[![License](https://img.shields.io/packagist/l/stevebauman/translation.svg?style=flat-square)](https://packagist.org/packages/stevebauman/translation)

## Description

Translation is a developer friendly, database driven, automatic translator for Laravel 5. Wouldn't it be nice to just write text regularly
on your application and have it automatically translated, added to the database, and cached at runtime? Take this for example:

Controller:

    public function index()
    {
        return view('home.index');
    }

View:

    @extends('layout.default')
    
    {{ _t('Welcome to our home page') }}

Seen:

    Welcome to our home page

When you visit the page, you won't notice anything different, but if you take a look at your database, your default
application locale has already been created, and the translation attached to that locale.

Now if we set locale to something different, such as French (fr), it'll automatically translate it for you.

Controller:

    public function index()
    {
        Translation::setLocale('fr');
        
        return view('home.index');
    }
    
View:

    @extends('layout.default')
    
    {{ _t('Welcome to our home page') }}

Seen:

    Bienvenue sur notre page d'accueil

We can even use placeholders for dynamic content:

View:

    {{ _t('Welcome :name, to our home page', ['name' => 'John']) }}

Seen:

    Bienvenue John , à notre page d'accueil

Notice that we didn't actually change the text inside the view, which means everything stays completely readable in your
locale to you (the developer!), which means no more managing tons of translation files and trying to decipher what text may be inside that dot-notated translation
path:

    {{ trans('page.home.index.welcome') }}
    
## Installation

Require the translation package 

    composer require stevebauman/translation

Add the service provider to your `config/app.php` config file

    'Stevebauman\Translation\TranslationServiceProvider',
    
Add the facade to your aliases in your `config/app.php` config file

    'Translation' => 'Stevebauman\Translation\Facades\Translation',
    
Publish the migrations

    php artisan vendor:publish --provider="Stevebauman\Translation\TranslationServiceProvider"
    
Run the migrations

    php artisan migrate

Your good to go!

## Usage

Anywhere in your application, either use the the shorthand function (can be disabled in config file)

    _t('Translate me!')
    
Or

    Translation::translate('Translate me!')
    
This is typically most useful in blade views:

    {{ _t('Translate me!') }}
    
And you can even translate models easily by just plugging in your content:

    {{ _t($post->title) }}

Or use placeholders:

    {{ _t('Post :title', ['title' => $post->title]) }}

In your `locales` database table you'll have:

    | id | code |  name  | display_name | lang_code |
       1    en    English      NULL          NULL

In your `translations` database table you'll have:

    | id | locale_id | translation_id | translation |
      1        NULL         NULL        'Translate me!'

To switch languages for the users session, all you need to call is:

    Translation::setLocale('fr') // Setting to French locale

Locales are automatically created when you call the `Translation::setLocale($code)` method,
and when the translate function is called, it will automatically create a new translation record
for the new locale, with the default locale translation. The default locale is taken from the laravel `app.php` config file.

Now, once you visit the page you'll have this in your `locales` table:

    | id | code | name | display_name | lang_code |
       1    en    English     NULL         NULL
       2    fr    French      NULL         NULL

And this in your `translations` table:

    | id | locale_id | translation_id | translation |
       1        1         NULL        'Translate me!'
       2        2          1          'Traduisez-moi !'

You can now update the translation on the new record and it will be shown wherever it's called:

    _t('Translate me!')`

###### Need to translate a single piece of text without setting the users default locale?

Just pass in the locale into the third argument inside the translation functions show above like so:


View:

    {{ _t('Our website also supports russian!', [], 'ru') }}
    
    <br>
    
    {{ _t('And french!', [], 'fr') }}

Seen:

    Наш сайт также поддерживает России !
    
    Et françaises !
    
This is great for showing users that your site supports different languages without changing the entire site
language itself. You can also perform replacements like usual:

View:

    {{ _t('Hello :name, we also support french!', ['name' => 'John Doe'], 'fr') }}

Seen:

    Bonjour John Doe , nous soutenons aussi le français !

Performing this will also create the locale in your database, save the translation, and cache it in case you need it again.

You must provide you're own way of updating translations (controllers/views etc) using the eloquent models provided.

## Injecting Translation

As of `v1.3.4` you can now inject the `Translation` contract into your controllers without the use of a facade:

```php
use Stevebauman\Translation\Contracts\Translation;

class BlogController extends Controller
{
    /**
     * @var Translation
     */
    protected $translation;
    
    /**
     * Constructor.
     *
     * @param Translation $translation
     */
    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }
    
    /**
     * Returns all blog entries.
     *
     * @return Illuminate\View\View
     */
    public function index()
    {
        $title = $this->translation->translate('My Blog');
        
        $entries = Blog::all();
        
        return view('pages.blog.index', compact('title', 'entries'));
    }
}
```

## Models

By default, two models are included and selected inside the configuration file. If you'd like to use your own models
you must create them and implement their trait. Here's an example:

The Locale Model:
    
    use Stevebauman\Translation\Traits\LocaleTrait;
    use Illuminate\Database\Eloquent\Model;
    
    class Locale extends Model
    {
        use LocaleTrait;
    
        /**
         * The locales table.
         *
         * @var string
         */
        protected $table = 'locales';
    
        /**
         * The fillable locale attributes.
         *
         * @var array
         */
        protected $fillable = [
            'code',
            'lang_code',
            'name',
            'display_name',
        ];
    
        /**
         * {@inheritdoc]
         */
        public function translations()
        {
            return $this->hasMany(Translation::class);
        }
    }

The Translation Model:

    use Stevebauman\Translation\Traits\TranslationTrait;
    use Illuminate\Database\Eloquent\Model;
    
    class Translation extends Model
    {
        use TranslationTrait;
    
        /**
         * The locale translations table.
         *
         * @var string
         */
        protected $table = 'translations';
    
        /**
         * The fillable locale translation attributes.
         *
         * @var array
         */
        protected $fillable = [
            'locale_id',
            'translation_id',
            'translation',
        ];
    
        /**
         * {@inheritdoc}
         */
        public function locale()
        {
            return $this->belongsTo(Locale::class);
        }
    
        /**
         * {@inheritdoc}
         */
        public function parent()
        {
            return $this->belongsTo(self::class);
        }
    }

Once you've created these models, insert them into the `translation.php` configuration file:

    /*
    |--------------------------------------------------------------------------
    | Locale Model
    |--------------------------------------------------------------------------
    |
    |  The locale model is used for storing locales such as `en` or `fr`.
    |
    */

    'locale' => App\Models\Locale::class,


    /*
    |--------------------------------------------------------------------------
    | Translation Model
    |--------------------------------------------------------------------------
    |
    |  The translation model is used for storing translations.
    |
    */

    'translation' => App\Models\Translation::class,

## Routes

Translating your site with a locale prefix couldn't be easier. First inside your `app/Http/Kernel.php` file, insert
the locale middleware:

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        
        // Insert Locale Middleware
        'locale' => \Stevebauman\Translation\Middleware\LocaleMiddleware::class
    ];

Now, in your `app/Http/routes.php` file, insert the middleware and the following Translation method in the route
group prefix like so:

    Route::group(['prefix' => Translation::getRoutePrefix(), 'middleware' => ['locale']], function()
    {
        Route::get('home', function ()
        {
            return view('home');
        });
    });

You should now be able to access routes such as:

    http://localhost/home
    http://localhost/en/home
    http://localhost/fr/home

## Automatic Translation

Automatic translation is enabled by default in the configuration file. It utilizes the fantastic package 
[Google Translate PHP](https://github.com/Stichoza/google-translate-php) by [Stichoza](https://github.com/Stichoza). 
Using automatic translation will send the inserted text to google and save the returned text to the database. Once a 
translation is saved in the database, it is never sent back to google to get re-translated. This means 
that you don't have to worry about hitting a cap that google may impose. You effectively <b>own</b> that translation.

## Questions / Concerns

#### Why are there underscores where my placeholders should be in my database translations?

When you add placeholders to your translation, and add the data to replace it, for example:

    _t('Hi :name', ['name' => 'John'])
    
Translation parses each entry in the data array to see if the placeholder actually exists for the data inserted. For example,
in the translation field in your database, here is what is saved:

    _t('Hi :name', ['name' => 'John']) // Hi __name__
    
    _t('Hi :name', ['test' => 'John']) // Hi :name
    
Since the placeholder data inserted doesn't match a placeholder inside the string, the text will be left as is. The
reason for the underscores is because google translate will try to translate text containing `:name`, however providing
double underscores on both sides of the placeholder, prevents google from translating that specific word, allowing us to translate
everything else, but keep placeholders in tact. Translation then replaces the double underscore variant of the placeholder
(in this case `__name__`) at runtime.

#### If I update / modify the text inside the translation function, what happens to it's translations?

If you modify the text inside a translation function, it will create a new record and you will need to translate it again.
This is intended because it could be a completely different translation after modification.

For example using:

    {{ _t('Welcome!') }}
    
And modifying it to:

    {{ _t('Welcome') }}

Would automatically generate a new translation record.

#### Is there a maximum amount of text that can be auto-translated?

Update: This package now uses [Stichoza's](https://github.com/Stichoza) new 3.0 update. This allows you to translate
up to 4200 words per request (tested, possibly more allowed).

<del>Yes, according to [Google Translate PHP](https://github.com/Stichoza/google-translate-php/issues/8) there is a 1300 word
limit <b>per request</b>. Just be sure to break you're content up so you don't hit the limit.</del>

## Issues

#### I'm trying to set the locale in my routes file but it never changes?

Are you using the `file` driver for sessions by chance? This is a known issue with the Laravel 5 file session driver https://github.com/laravel/framework/issues/8244.

Use another session driver for the time being, such as array or database.
