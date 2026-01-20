<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call(UsuariosTableSeeder::class);
        $this->call(MenusTableSeeder::class);
        $this->call(PermisosSeeder::class);

        $this->call(EstadosTableSeeder::class);
        $this->call(RegionalesTableSeeder::class);
        $this->call(MunicipiosTableSeeder::class);
        $this->call(LocalidadesTableSeeder::class);
        $this->call(TiposUnidadesTableSeeder::class);
        $this->call(TipologiasUnidadesTableSeeder::class);
        $this->call(InstitucionesTableSeeder::class);
        $this->call(EstratosTableSeeder::class);
        $this->call(TiposVialidadesTableSeeder::class);
        $this->call(TiposAsentamientosTableSeeder::class);
        $this->call(TiposAdministracionTableSeeder::class);
        $this->call(StatusUnidadesTableSeeder::class);
        $this->call(StatusPropiedadesTableSeeder::class);
        $this->call(ProfesionesTableSeeder::class);
        $this->call(TiposEstablecimientoTableSeeder::class);
        $this->call(SubtipologiasTableSeeder::class);
        $this->call(MotivosBajaTableSeeder::class);
        $this->call(NivelesAtencionTableSeeder::class);
        $this->call(UMMarcasTableSeeder::class);
        $this->call(UMProgramasTableSeeder::class);
        $this->call(UMTiposTableSeeder::class);
        $this->call(UMTipologiasTableSeeder::class);
        $this->call(UnidadesTableSeeder::class);
    }
}
