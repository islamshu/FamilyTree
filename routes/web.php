<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'FamilyTree')->name('home');
Route::inertia('/tree-chart', 'FamilyTreeChart')->name('tree-chart');
