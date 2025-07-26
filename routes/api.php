<?php
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;
use App\Models\User;

Route::middleware([NeedsTenant::class])->get('/', fn() => ['Teste']);

Route::middleware([NeedsTenant::class])->get('/create', function () {
    User::factory()->count(10)->create();
    return ['Usuarios' => User::all()];
});

Route::middleware([NeedsTenant::class])->get('/show', fn() => ['Usuarios' => User::all()]);
