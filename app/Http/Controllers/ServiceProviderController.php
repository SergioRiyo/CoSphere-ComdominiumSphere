<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchiveServiceProviderRequest;
use App\Http\Requests\IndexServiceProviderRequest;
use App\Http\Requests\SaveServiceProviderRequest;
use App\Models\ServiceProvider;
use App\Services\ServiceProviderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceProviderController extends Controller
{
    public function index(IndexServiceProviderRequest $request, ServiceProviderService $service): Response
    {
        return Inertia::render('admin/service-providers', ['providers' => $service->paginate($request->validated()), 'filters' => $request->safe()->except('page')]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ServiceProvider::class);

        return Inertia::render('admin/service-provider-form', ['provider' => null]);
    }

    public function store(SaveServiceProviderRequest $request, ServiceProviderService $service): RedirectResponse
    {
        return to_route('admin.service-providers.show', $service->save($request->user(), $request->validated()));
    }

    public function show(ServiceProvider $serviceProvider): Response
    {
        Gate::authorize('view', $serviceProvider);

        return Inertia::render('admin/service-provider-form', ['provider' => $serviceProvider->only(['id', 'name', 'phone', 'email', 'specialty', 'cpf_cnpj', 'deleted_at'])]);
    }

    public function update(SaveServiceProviderRequest $request, ServiceProvider $serviceProvider, ServiceProviderService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated(), $serviceProvider);

        return to_route('admin.service-providers.show', $serviceProvider);
    }

    public function archive(ArchiveServiceProviderRequest $request, ServiceProvider $serviceProvider, ServiceProviderService $service): RedirectResponse
    {
        $service->archive($request->user(), $serviceProvider);

        return to_route('admin.service-providers.index');
    }
}
