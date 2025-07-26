<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use function Laravel\Prompts\text;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create';
    protected $description = 'Cria um tenant com subdomínio e banco de dados isolado';

    public function handle()
    {
        $name = text('Give me a name');
        $domain = text('Give me a domain');
        $database = 'tenant_' . explode('.', $domain)[0];

        // Verifica se o banco já existe
        $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$database]);

        if (empty($exists)) {
            DB::statement("CREATE DATABASE `$database`");
            $this->info("Banco criado.");
        } else {
            $this->info("Banco $database já existe.");
        }

        // Verifica se já existe um tenant com o mesmo domínio ou banco
        $exists = Tenant::where('domain', $domain)
            ->orWhere('database', $database)
            ->first();

        if ($exists) {
            $this->info("Já existe um tenant com domínio ou banco de dados informados.");
        } else {
            Tenant::create([
                'name' => $name,
                'domain' => $domain,
                'database' => $database
            ]);
            $this->info("Tenant criado com domínio $domain e banco $database");
        }

        config()->set('database.connections.tenant.database', $database);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => '/database/migrations',
            '--force' => true,
        ]);
    }
}
