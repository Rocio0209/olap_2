<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->unsignedInteger('idestado');
            $table->unsignedInteger('idregional');
            $table->unsignedInteger('idmunicipio');
            $table->string('municipio');
            $table->primary(['idestado', 'idmunicipio']);
            $table->foreign('idestado')->references('idestado')->on('estados')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign(['idestado', 'idregional'])->references(['idestado', 'idregional'])->on('regionales')->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE municipios CHANGE idestado idestado INT(2) UNSIGNED ZEROFILL NOT NULL');
        DB::statement('ALTER TABLE municipios CHANGE idregional idregional INT(2) UNSIGNED ZEROFILL NOT NULL');
        DB::statement('ALTER TABLE municipios CHANGE idmunicipio idmunicipio INT(3) UNSIGNED ZEROFILL NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
