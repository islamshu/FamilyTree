<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/manage/users/create', [UserController::class, 'create'])->name('users.create');
Route::post('/manage/users', [UserController::class, 'store'])->name('users.store');

Route::inertia('/', 'FamilyTree')->name('home');
Route::inertia('/tree-chart', 'FamilyTreeChart')->name('tree-chart');
Route::inertia('/tree-chart-full', 'FamilyTreeChartFull')->name('tree-chart-full');
