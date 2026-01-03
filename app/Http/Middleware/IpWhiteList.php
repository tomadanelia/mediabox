<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpWhiteList
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->ip() !== '10.0.0.16' && $request->ip() !== '127.0.0.1', 403, 'Unauthorized IP');

        return $next($request);
    }
}

