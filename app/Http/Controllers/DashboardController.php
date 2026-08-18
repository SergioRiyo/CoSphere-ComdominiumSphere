<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $adminDashboardService) {}

    public function admin(): Response
    {
        return Inertia::render('admin/dashboard', [
            'metrics' => $this->adminDashboardService->metrics(),
        ]);
    }

    public function morador(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('morador/dashboard', [
            'unit' => $user->unit()->first(['id', 'block', 'number', 'type', 'complement']),
        ]);
    }

    public function porteiro(): Response
    {
        return Inertia::render('portaria/dashboard');
    }
}
