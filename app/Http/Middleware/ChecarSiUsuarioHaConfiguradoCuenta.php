<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChecarSiUsuarioHaConfiguradoCuenta
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()->hasRole('Administrador General') && $request->user()->changePass == 0 && $request->user()->can(['ver_perfil', 'updt_password'])) { //Si el usuario no ha cambiado su contraseña y tiene los permisos para hacerlo
            return redirect()->route('profile.show');
        }
        return $next($request);
    }
}
