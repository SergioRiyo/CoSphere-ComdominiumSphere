<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPortariaVisitorAccessHistoryRequest;
use App\Services\VisitorAccessQueryService;
use Inertia\Inertia;
use Inertia\Response;

class PortariaVisitorAccessHistoryController extends Controller
{
    public function __construct(private VisitorAccessQueryService $visitorAccessQueryService) {}

    public function index(IndexPortariaVisitorAccessHistoryRequest $request): Response
    {
        $filters = $request->validated();

        if (isset($filters['unit_id'])) {
            $filters['unit_id'] = (int) $filters['unit_id'];
        }

        return Inertia::render('portaria/visitor-access-history/index', [
            'accesses' => $this->visitorAccessQueryService->paginateHistoryForPortaria($filters),
            'unitOptions' => $this->visitorAccessQueryService->unitOptions(),
            'situationOptions' => [
                ['value' => 'present', 'label' => 'Presente'],
                ['value' => 'finished', 'label' => 'Finalizado'],
                ['value' => 'denied', 'label' => 'Negado'],
                ['value' => 'pending', 'label' => 'Aguardando'],
                ['value' => 'validated', 'label' => 'Validado'],
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'unit_id' => $filters['unit_id'] ?? null,
                'situation' => $filters['situation'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'timezone' => config('app.timezone'),
        ]);
    }
}
