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
        $filters = $request->safe()->except(['accesses_page']);

        return Inertia::render('admin/reports-overview', $reports->data($request->validated()) + ['filters' => $filters]);
    }
}
