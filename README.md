![Translation Banner]
(https://github.com/stevebauman/translation/blob/master/translation-banner.jpg)

[![Code Climate](https://codeclimate.com/github/stevebauman/translation/badges/gpa.svg)](https://codeclimate.com/github/stevebauman/translation)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/stevebauman/translation/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/stevebauman/translation/?branch=master)
[![Build Status](https://travis-ci.org/stevebauman/translation.svg)](https://travis-ci.org/stevebauman/translation)
[![Latest Stable Version](https://poser.pugx.org/stevebauman/translation/v/stable.svg)](https://packagist.org/packages/stevebauman/translation)
[![Total Downloads](https://poser.pugx.org/stevebauman/translation/downloads.svg)](https://packagist.org/packages/stevebauman/translation)
[![License](https://poser.pugx.org/stevebauman/translation/license.svg)](https://packagist.org/packages/stevebauman/translation)

## Description

Translation is a database driven, automatic translator for Laravel 4 / 5. Wouldn't it be nice to just write text regularly
on your application and have it automatically added to the database, cached, and translated at runtime? Take this for example:

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

We can even use placeholders for dynamic content (added in `v1.1.0`)

View:

    {{ _t('Welcome :name, to our home page', array('name' => 'John')) }}

Seen:

    Bienvenue John , à notre page d'accueil

Notice that we didn't actually change the text inside the view, which means everything stays completely readable in your
locale to you (the developer!), which means no more managing tons of translation files and trying to decipher what text may be inside that dot-notated translation
path:

    {{ trans('page.home.index.welcome') }}
    
## Installation

Require translation in your composer.json file

    "stevebauman/translation": "1.1.*"
    
Then run the composer update command on your project source

    composer update
    
#### Laravel 4
    
Add the service provider to your `app/config/app.php` config file

    'Stevebauman\Translation\TranslationServiceProvider',
    
Add the facade to your aliases in your `app/config/app.php` config file

    'Translation' => 'Stevebauman\Translation\Facades\Translation',
    
Run the migrations

    php artisan migrate --package="stevebauman/translation"
    
Your good to go!

#### Laravel 5

Add the service provider to your `config/app.php` config file

    'Stevebauman\Translation\TranslationServiceProvider',
    
Add the facade to your aliases in your `config/app.php` config file

    'Translation' => 'Stevebauman\Translation\Facades\Translation',
    
Publish the migrations

    php artisan vendor:publish
    
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

    {{ _t('Post :title', array('title' => $post->title)) }}

In your `locales` database table you'll have:

    | id | code |  name  | display_name | lang_code |
       1    en    English      NULL          NULL

In your `locale_translations` database table you'll have:

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

And this in your `locale_translations` table:

    | id | locale_id | translation_id | translation |
       1        1         NULL        'Translate me!'
       2        2          1          'Traduisez-moi !'

You can now update the translation on the new record and it will be shown wherever:

    _t('Translate me!')`
    
Is called.

You must provide you're own way of updating translations (controllers/views etc) using the eloquent models provided.

## Automatic Translation

Automatic translation is enabled by default in the configuration file. It utilizes the fantastic package 
[Google Translate PHP](https://github.com/Stichoza/google-translate-php) by [Stichoza](https://github.com/Stichoza). 
Using automatic translation will send the inserted text to google and save the returned text to the database. Once a 
translation is saved in the database, it is never sent back to google to get re-translated. This means 
that you don't have to worry about hitting a cap that google may impose. You effectively <b>own</b> that translation.

## Commands

#### Scan

The scan command accepts one argument (directory) and one option (locale). It will look through each file in the directory
given and add the translation in the database that has the format of:

    _t('')
    _t("")
    Translation::translate('')
    Translation::translate("")

To perform the scan, use artisan like so:

    php artisan translation:scan directory --locale="code"

Specifying the directory is mandatory.

For example, to scan your views directory, use:

    php artisan translation:scan app/views --locale="en"
   
If you specify a locale, it will add all the translations for the app locale, as well as the specified locale.

For example if your default app locale was 'en', and you supply 'fr' to the locale option, it will generate the translation records
for both locales.

## Questions / Concerns

#### Why are there underscores where my placeholders should be in my database translations?

When you add placeholders to your translation, and add the data to replace it, for example:

    _t('Hi :name', array('name' => 'John'))
    
Translation parses each entry in the data array to see if the placeholder actually exists for the data inserted. For example,
in the translation field in your database, here is what is saved:

    _t('Hi :name', array('name' => 'John')) // Hi __name__
    
    _t('Hi :name', array('test' => 'John')) // Hi :name
    
Since the placeholder data inserted doesn't match a placeholder inside the string, the text will be left as is. The
reason for the underscores is because google translate will try to translate text containing `:name`, however providing
double underscores on both sides of the placeholder, prevents google from translating that specific word, allowing us to translate
everything else, but keep placeholders in tact. Translation then replaces the double underscore variant of the placeholder
(in this case `__name__`) at runtime.
    
#### Will my translations be erased / modified if I run the scan command?

No. No translations are ever removed or updated from the scan command. Translations are only added. You will have to manage
translations that are no longer in use. This may be changed in the future.

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

Are you using Laravel 5 and the `file` driver for sessions by chance? This is a known issue with the Laravel 5 file session driver https://github.com/laravel/framework/issues/8244.

Use another session driver for the time being, such as array or database.