<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddIsRelevantField extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->boolean('is_relevant')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('translations', function (Blueprint $table) {
            $table->dropColumn('is_relevant');
        });
    }
}
