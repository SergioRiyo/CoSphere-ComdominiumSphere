<?php

namespace App\Http\Controllers;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Http\Requests\IndexIncidentRequest;
use App\Http\Requests\StartIncidentMaintenanceRequest;
use App\Http\Requests\UpdateIncidentPriorityRequest;
use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Services\IncidentQueryService;
use App\Services\IncidentService;
use App\Services\MaintenanceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminIncidentController extends Controller
{
    public function index(IndexIncidentRequest $request, IncidentQueryService $query): Response
    {
        return Inertia::render('admin/incidents', [
            'incidents' => $query->paginate($request->user(), $request->validated()),
            'filters' => $request->safe()->except('page'),
            'options' => $query->options($request->user()),
        ]);
    }

    public function show(Request $request, int $incident, IncidentQueryService $query): Response
    {
        return Inertia::render('admin/incident-details', [
            'incident' => $query->details($request->user(), $incident),
            'priorities' => $query->options($request->user())['priorities'],
        ]);
    }

    public function status(UpdateIncidentStatusRequest $request, Incident $incident, IncidentService $service): JsonResponse
    {
        $service->transition($request->user(), $incident, IncidentStatus::from($request->validated('status')), $request->validated('reason'));

        return response()->json(['message' => 'Status atualizado.']);
    }

    public function priority(UpdateIncidentPriorityRequest $request, Incident $incident, IncidentService $service): JsonResponse
    {
        $service->updatePriority($request->user(), $incident, IncidentPriority::from($request->validated('priority')));

        return response()->json(['message' => 'Prioridade salva.']);
    }

    public function maintenance(StartIncidentMaintenanceRequest $request, Incident $incident, MaintenanceRequestService $service): JsonResponse
    {
        $service->createFromIncident($request->user(), $incident);

        return response()->json(['message' => 'Manutenção vinculada com sucesso.'], 201);
    }
}
