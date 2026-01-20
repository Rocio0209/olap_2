<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LoginSuccessful
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $request = request();

        $event->subject = 'login';
        $event->description = 'Inicio de Sesión Correcto';
        activity($event->subject)->by($event->user)->withProperties(['user' => $request->username])->log($event->description);
    }
}
