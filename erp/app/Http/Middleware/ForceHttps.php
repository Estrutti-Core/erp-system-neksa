<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Baseado no APP_URL e nao no ambiente: uma instalacao em rede local roda
        // em HTTP puro e redirecionar para https ali gera loop de redirecionamento.
        if (!$request->secure() && str_starts_with((string) config('app.url'), 'https://')) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
