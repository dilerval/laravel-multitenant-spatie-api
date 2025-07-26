# 🏗️ Laravel Multitenancy com Subdomínios (Spatie + Apache) - Debian 12

Este guia documenta a criação de um ambiente multitenant em Laravel utilizando subdomínios, o pacote `spatie/laravel-multitenancy`, e configurações no Apache para desenvolvimento local em Debian 12.

---

## 🔧 1. Configurar domínio local com subdomínios

### Editar `/etc/hosts`

```bash
sudo nano /etc/hosts
127.0.0.1 tenant.test
127.0.0.1 cliente1.tenant.test
127.0.0.1 cliente2.tenant.test
```

## 🌐 2. Configurar Apache

### Editar `/etc/hosts`
Instalar e habilitar módulos:

```bash
sudo apt install apache2
sudo a2enmod rewrite
```

Criar VirtualHost:
```bash
sudo nano /etc/apache2/sites-available/tenant.test.conf
```

Conteúdo:
```bash
<VirtualHost *:80>
    ServerName tenant.test
    ServerAlias *.tenant.test
    DocumentRoot /caminho/para/laravel-multitenant-spatie-api/public

    <Directory /caminho/para/laravel-multitenant-spatie-api/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Habilitar site e reiniciar Apache:
```bash
sudo a2ensite tenant.test.conf
sudo systemctl reload apache2
```

## 🚀 3. Criar projeto Laravel


```bash
composer create-project laravel/laravel laravel-multitenant-spatie-api
cd laravel-multitenant-spatie-api
cp .env.example .env
php artisan key:generate

```


Configurar .env
```bash
APP_NAME="Multitenant API"
APP_URL=http://tenant.test
APP_DOMAIN=tenant.test
DB_CONNECTION=tenant
DB_DATABASE=multitenant
DB_USERNAME=root
DB_PASSWORD=senha

```

Criar banco landlord
```bash
mysql -u root -p -e "CREATE DATABASE multitenant;"

```



## 📦 4. Instalar e configurar Spatie Laravel Multitenancy
```bash
composer require spatie/laravel-multitenancy
php artisan vendor:publish --tag="multitenancy-config"
php artisan vendor:publish --tag="multitenancy-migrations"

```


Editar config/database.php
```php
'default' => env('DB_CONNECTION', 'tenant'),

'tenant' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => null, // será definido dinamicamente
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],

// Altere o nome da conexão mariadb
'mariadb' => []

// para landlord
'landlord' => []

```
Preparando banco e criar tabelas em landlord
```bash
php artisan migrate --database=landlord --path=database/migrations/landlord

```


Conceder permissões:
```bash
sudo chown -R www-data:www-data storage
sudo chown -R www-data:www-data bootstrap/cache
sudo chown -R www-data:www-data storage/logs
```


Criar modelo Tenant
```bash
php artisan make:model Tenant

```


```php
// app/Models/Tenant.php
namespace App\Models;

use Spatie\Multitenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    protected $fillable = ['name', 'domain', 'database'];
}

```

Editar config/multitenancy.php
```php
'tenant_finder' => Spatie\Multitenancy\TenantFinder\DomainTenantFinder::class,
'tenant_model' => App\Models\Tenant::class,
'tenant_database_connection_name' => 'tenant',
'landlord_database_connection_name' => 'landlord',
'switch_tenant_tasks' => [ \Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask::class,],
```


## ⚙️ 5. Criar comando Artisan para registrar tenant

```bash
php artisan make:command CreateTenantCommand

```

Conteúdo:
```php
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

```


## 🛣️ 6. Criar rotas da API

Configure no bootstrap/app.php:

```php
// Adicione o arquivo api.php
->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
```

```php
// routes/api.php

use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;
use App\Models\User;

Route::get('/', fn() => ['Teste']);

Route::get('/create', function () {
    User::factory()->count(10)->create();
    return ['Usuarios' => User::all()];
});

Route::get('/show', fn() => ['Usuarios' => User::all()]);

```

## 🧪 7. Testando os subdomínios

Rodar comando para criar um cliente:
```bash
sudo -u www-data php artisan tenant:create

 ┌ Give me a name ──────────────────────────────────────────────┐
 │ cliente1                                                     │
 └──────────────────────────────────────────────────────────────┘

 ┌ Give me a domain ────────────────────────────────────────────┐
 │ cliente1.tenant.test                                         │
 └──────────────────────────────────────────────────────────────┘

```

- http://cliente1.tenant.test/api/create

- http://cliente2.tenant.test/api/create

- http://cliente1.tenant.test/api/show

- http://cliente2.tenant.test/api/show

## 📌 8. Observações

- O nome do banco é derivado do subdomínio (tenant_cliente1, por exemplo).

- Os migrations dos tenants devem estar em /database/migrations.

- Os migrations do landlord devem estar em /database/migrations/landlord.

- O middleware SetTenantByDomain pode ser customizado para lógica adicional.

- É importante isolar bem as responsabilidades entre landlord e tenants.


