<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLocalesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('locales', function (Blueprint $table)
        {
            $table->increments('id');
            $table->timestamps();
            $table->string('code')->unique();
            $table->string('lang_code')->nullable();
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('locales');
    }
}
