<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input)
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $redireccionar = $user->changePass == 1 ? false : true;

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'changePass' => 1
        ])->save();

        activity('Perfil')->performedOn($user)->causedBy($user)->log('Cambio de contraseña'); //Guardando en actividades el cambio de contraseña por parte del usuario

        if ($redireccionar) { //En caso de que cambie su contraseña por primera vez se le redirige a su panel
            return redirect()->route('login');
        }
    }
}
