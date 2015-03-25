<?php

namespace Stevebauman\Translation\Tests;

use Illuminate\Database\Capsule\Manager as DB;

abstract class FunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->configureDatabase();
        $this->migrateTables();
    }

    protected function configureDatabase()
    {
        $db = new DB;

        $db->addConnection(array(
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ));

        $db->bootEloquent();

        $db->setAsGlobal();
    }

    public function migrateTables()
    {
        DB::schema()->create('locales', function ($table)
        {
            $table->increments('id');
            $table->timestamps();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('lang_code')->nullable();
        });

        DB::schema()->create('locale_translations', function ($table)
        {
            $table->increments('id');
            $table->timestamps();
            $table->integer('locale_id')->unsigned();
            $table->integer('translation_id')->unsigned()->nullable();
            $table->text('translation');

            $table->foreign('locale_id')->references('id')->on('locales')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            $table->foreign('translation_id')->references('id')->on('locale_translations')
                ->onUpdate('restrict')
                ->onDelete('cascade');
        });
    }
}