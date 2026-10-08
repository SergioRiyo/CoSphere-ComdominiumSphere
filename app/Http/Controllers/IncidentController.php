<?php

namespace App\Http\Controllers;

use App\Enums\IncidentCategory;
use App\Http\Requests\IndexIncidentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Models\Incident;
use App\Services\IncidentQueryService;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function index(IndexIncidentRequest $request, IncidentQueryService $query): Response
    {
        return Inertia::render('morador/incidents', [
            'incidents' => $query->paginate($request->user(), $request->validated()),
            'filters' => $request->safe()->except('page'),
            'options' => $query->options($request->user()),
            'can_create' => $request->user()->can('create', Incident::class),
        ]);
    }

    public function create(Request $request, IncidentQueryService $query): Response
    {
        Gate::authorize('create', Incident::class);

        return Inertia::render('morador/incident-create', [
            'options' => [
                'types' => $query->options($request->user())['types'],
                'categories' => array_map(fn (IncidentCategory $category): array => [
                    'value' => $category->value, 'label' => $category->label(),
                ], IncidentCategory::cases()),
            ],
        ]);
    }

    public function store(StoreIncidentRequest $request, IncidentService $service): RedirectResponse
    {
        $incident = $service->create($request->user(), $request->safe()->except('attachments'), $request->file('attachments', []));

        return to_route('morador.incidents.show', $incident);
    }

    public function show(Request $request, int $incident, IncidentQueryService $query): Response
    {
        return Inertia::render('morador/incident-details', ['incident' => $query->details($request->user(), $incident)]);
    }
}
