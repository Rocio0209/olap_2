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
        Schema::create('regionales', function (Blueprint $table) {
            $table->unsignedInteger('idestado');
            $table->unsignedInteger('idregional');
            $table->string('regional');
            $table->primary(['idestado', 'idregional']);
            $table->foreign('idestado')->references('idestado')->on('estados')->onDelete('RESTRICT')->onUpdate('CASCADE');
        });

        DB::statement('ALTER TABLE regionales CHANGE idestado idestado INT(2) UNSIGNED ZEROFILL NOT NULL');
        DB::statement('ALTER TABLE regionales CHANGE idregional idregional INT(2) UNSIGNED ZEROFILL NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regionales');
    }
};
