<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SetTenantByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost(); // Exemplo: cliente1.tenant.test

        // Busca o tenant com base no domínio completo
        $tenant = Tenant::where('domain', $host)->first();

        if (!$tenant) {
            abort(404, 'Tenant não encontrado');
        }

        // Define a base do tenant dinamicamente
        config()->set('database.connections.tenant.database', $tenant->database);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Define o tenant atual na Spatie
        $tenant->makeCurrent();

        return $next($request);
    }
}
