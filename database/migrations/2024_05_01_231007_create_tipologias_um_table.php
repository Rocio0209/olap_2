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
        Schema::create('tipologias_um', function (Blueprint $table) {
            $table->unsignedInteger('idtipologia_um');
            $table->string('tipologia_um');
            $table->primary('idtipologia_um');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipologias_um');
    }
};
