<?php
use Illuminate\Support\Facades\Route;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;
use App\Models\User;

Route::get('/', fn() => ['Teste']);

Route::get('/create', function () {
    User::factory()->count(10)->create();
    return ['Usuarios' => User::all()];
});

Route::get('/show', fn() => ['Usuarios' => User::all()]);
