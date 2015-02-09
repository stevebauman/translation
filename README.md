![Translation Banner]
(https://github.com/stevebauman/translation/blob/master/translation-banner.jpg)

##Installation

Require translation in your composer.json file

    "stevebauman/translation": "1.0.*"
    
Then run the composer update command on your project source

    composer update
    
Add the service provider to your `app.php` config file

    'Stevebauman\Translation\TranslationServiceProvider',
    
Add the facade to your aliases in your `app.php` config file

    'Translation' => 'Stevebauman\Translation\Facades\Translation',
    
Run the migrations

    php artisan migrate --package="stevebauman/translation"
    
Your good to go!
    
##Usage

Anywhere in your application, either use the the shorthand function (can be disabled in config file)

    _t('Translate me!')
    
Or

    Translation::translate('Translate me!')
    
This is typically most useful in blade views:

    {{ _t('Translate me!') }}
    
And you can even translate models easy by just plugging in your content:

    {{ _t($post->title) }}
    
In your `locales` database table you'll have:

    | id | code | name | lang_code |
       1    en    NULL      NULL

In your `translations` database table you'll have:

    | id | locale_id | translation_id | translation |
      1        NULL         NULL        'Translate me!'

To switch languages for the users session, all you need to call is:

    Translation::setLocale('fr') // Setting to French locale

Locales are automatically created when you call the `Translation::setLocale($code)` method,
and when the translate function is called, it will automatically create a new translation record
for the new locale, with the default locale translation. The default locale is taken from the laravel `app.php` config file.

Now, once you visit the page you'll have this in your `locales` table:

    | id | code | name | lang_code |
       1    en    NULL      NULL
       2    fr    NULL      NULL

And this in your `translations` table:

    | id | locale_id | translation_id | translation |
       1        NULL         NULL        'Translate me!'
       2        NULL          1          'Translate me!'

You can now update the translation on the new record and it will be shown wherever `_t('Translate me!')` is called.

You must provide you're own way of updating translations (controllers/views etc).

##Commands

####Scan

The scan command accepts one argument (directory) and one option (locale). It will look through each file in the directory
and add the translation in the database that has the format of:

    _t('')
    _t("")
    Translation::translate('')
    Translation::translate("")

To perform the scan, use artisan like so:

    php artisan translation:scan directory --locale="en"

Specifying the directory is mandatory.

For example, to scan your views directory, use:

    php artisan translation:scan app/views
   
If you specify a locale, it will add all the translations for the app locale, as well as the specified locale.

For example if your default app locale was 'en', and you supply 'fr' to the locale option, it will generate the translation records
for both locales.