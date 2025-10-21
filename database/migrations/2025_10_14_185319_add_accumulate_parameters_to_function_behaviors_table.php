<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('function_behaviors', function (Blueprint $table) {
            $table->boolean('accumulate_parameters')->default(false)->after('prompt_enhancement');
        });
    }

    public function down()
    {
        Schema::table('function_behaviors', function (Blueprint $table) {
            $table->dropColumn('accumulate_parameters');
        });
    }
};