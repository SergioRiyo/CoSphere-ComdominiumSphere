<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardRedirectController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        return match ($request->user()?->role) {
            UserRole::Admin => to_route('admin.dashboard'),
            UserRole::Morador => to_route('morador.dashboard'),
            UserRole::Porteiro => to_route('portaria.dashboard'),
            default => abort(403),
        };
    }
}
