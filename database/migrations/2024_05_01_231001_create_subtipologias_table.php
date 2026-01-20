<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subtipologias', function (Blueprint $table) {
            $table->unsignedInteger('idsubtipologia')->autoIncrement();
            $table->string('subtipologia');
            $table->string('descripcion_subtipologia');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subtipologias');
    }
};
