<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityPolicyHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $csp = new \App\Support\ContentPolicy(); //Usando Laravel CSP para generar las cabeceras Content Security Policy
        $csp->configure();
        $respuesta = $next($request);
        $respuesta->headers->set('Content-Security-Policy', $csp->__toString(), true); //Agregando el COntent Security Policy
        $respuesta->headers->set('Permissions-Policy', 'camera=(), fullscreen=(self), geolocation=(), microphone=()', true); //Solo habilitando la pantalla completa y dehabilitando permisos que pudieran ser peligrosos
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', true); //Las referencias entre links solo se aplicaran para solicitudes HTTPS
        $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', true); // Previniendo ataques Man in the Middle con los protocolos HTTP y HTTPS 
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff', true); //Evitando los ataques MIME Sniffing si el navegador no puede determinar el tipo de archivo que se esta solicitando
        $respuesta->headers->set('X-Frame-Options', 'DENY', true); //La página no se podra mostrar en un IFrame
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow', true); //Agregando la cabecera para evitar que nuestros sitios sean indexados en los motores de busqueda
    
        return $respuesta; //Regresando la respuesta con las cabeceras
    }
}