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
        Schema::create('localidades', function (Blueprint $table) {
            $table->unsignedInteger('idestado');
            $table->unsignedInteger('idmunicipio');
            $table->unsignedInteger('idlocalidad');
            $table->string('localidad');
            $table->primary(['idestado', 'idmunicipio', 'idlocalidad']);
            $table->foreign(['idestado','idmunicipio'])->references(['idestado','idmunicipio'])->on('municipios')->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE localidades CHANGE idestado idestado INT(2) UNSIGNED ZEROFILL NOT NULL');
        DB::statement('ALTER TABLE localidades CHANGE idmunicipio idmunicipio INT(3) UNSIGNED ZEROFILL NOT NULL');
        DB::statement('ALTER TABLE localidades CHANGE idlocalidad idlocalidad INT(4) UNSIGNED ZEROFILL NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('localidades');
    }
};
