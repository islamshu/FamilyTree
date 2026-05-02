<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    public function store(CreateUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return back()->with('success', 'تم إنشاء المستخدم بنجاح');
    }
}
