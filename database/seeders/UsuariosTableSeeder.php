<?php

namespace Database\Seeders;

use App\Models\User;
use File;
use Illuminate\Database\Seeder;

class UsuariosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->delete();
        $json = File::get(__DIR__.'/json/users.json');
        $data = json_decode($json);

        foreach ($data as $item) {
            User::create(array(
                'id' => $item->id,
                'name' => $item->name,
                'email' => $item->email,
                'email_verified_at' => $item->email_verified_at,
                'username' => $item->username,
                'password' => $item->password,
                'two_factor_secret' => $item->two_factor_secret,
                'two_factor_recovery_codes' => $item->two_factor_recovery_codes,
                'two_factor_confirmed_at' => $item->two_factor_confirmed_at,
                'remember_token' => $item->remember_token,
                'current_team_id' => $item->current_team_id,
                'profile_photo_path' => $item->profile_photo_path,
                'idstatus_user' => $item->idstatus_user,
                'changePass' => $item->changePass,
            ));
        }
    }
}
