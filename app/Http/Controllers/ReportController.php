<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReportRequest;
use App\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(IndexReportRequest $request, ReportService $reports): Response
    {
        $filters = $request->safe()->except(['reservations_page', 'accesses_page', 'incidents_page']);

        return Inertia::render('admin/reports', $reports->data($request->validated()) + ['filters' => $filters]);
    }
}
