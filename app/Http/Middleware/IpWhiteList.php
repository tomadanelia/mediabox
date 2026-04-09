<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpWhiteList
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('services.monitoring.allowed_ips');

        if (!in_array($request->ip(), $allowedIps)) {
            abort(403, 'Unauthorized IP: ' . $request->ip());
        }

        return $next($request);
    }
}