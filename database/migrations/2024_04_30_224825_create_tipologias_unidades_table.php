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
        Schema::create('tipologias_unidades', function (Blueprint $table) {
            $table->unsignedInteger('idtipologia_unidad')->autoIncrement();
            $table->string('tipologia_unidad');
            $table->string('clave_tipologia');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipologias_unidades');
    }
};
