<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceRequestStatus;
use App\Http\Requests\IndexMaintenanceRequestRequest;
use App\Http\Requests\SaveMaintenanceRequestRequest;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceRequestQueryService;
use App\Services\MaintenanceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceRequestController extends Controller
{
    public function index(IndexMaintenanceRequestRequest $request, MaintenanceRequestQueryService $query): Response
    {
        return Inertia::render('admin/maintenances', ['maintenances' => $query->paginate($request->user(), $request->validated()), 'filters' => $request->safe()->except('page'), 'options' => $query->options($request->user(), true)]);
    }

    public function create(Request $request, MaintenanceRequestQueryService $query): Response
    {
        Gate::authorize('createAdministrative', MaintenanceRequest::class);

        return Inertia::render('admin/maintenance-form', ['maintenance' => null, 'options' => $query->options($request->user()), 'default_admin_id' => $request->user()->id]);
    }

    public function store(SaveMaintenanceRequestRequest $request, MaintenanceRequestService $service): RedirectResponse
    {
        return to_route('admin.maintenances.show', $service->createAdministrative($request->user(), $request->validated()));
    }

    public function show(Request $request, MaintenanceRequest $maintenance, MaintenanceRequestQueryService $query): Response
    {
        return Inertia::render('admin/maintenance-form', ['maintenance' => $query->details($request->user(), $maintenance), 'options' => $query->options($request->user()), 'default_admin_id' => $request->user()->id]);
    }

    public function update(SaveMaintenanceRequestRequest $request, MaintenanceRequest $maintenance, MaintenanceRequestService $service): RedirectResponse
    {
        $service->update($request->user(), $maintenance, $request->safe()->except(['status', 'reason']), $request->filled('status') ? MaintenanceRequestStatus::from($request->validated('status')) : null, $request->validated('reason'));

        return to_route('admin.maintenances.show', $maintenance);
    }
}
