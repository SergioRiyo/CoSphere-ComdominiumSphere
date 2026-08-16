<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function admin(): Response
    {
        return Inertia::render('admin/dashboard');
    }

    public function morador(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('morador/dashboard', [
            'unit' => $user->unit()->first(['id', 'number', 'type', 'complement']),
        ]);
    }

    public function porteiro(): Response
    {
        return Inertia::render('portaria/dashboard');
    }
}
