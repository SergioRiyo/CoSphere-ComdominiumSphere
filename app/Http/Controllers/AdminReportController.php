<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\ReportCsvService;
use App\Services\ReportQueryService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(ReportRequest $request, ReportQueryService $query): Response
    {
        return Inertia::render('admin/reports', $query->report($request->user(), $request->report(), $request->validated()));
    }

    public function export(ReportRequest $request, ReportCsvService $csv): StreamedResponse
    {
        return $csv->download($request->user(), $request->report(), $request->validated());
    }
}
