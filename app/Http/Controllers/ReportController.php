<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReportRequest;
use App\Services\ReportCsvService;
use App\Services\ReportService;
use App\Services\ReportSpreadsheetService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(IndexReportRequest $request, ReportService $reports): Response
    {
        $filters = $request->safe()->except(['accesses_page']);

        return Inertia::render('admin/reports', $reports->data($request->validated()) + ['filters' => $filters]);
    }

    public function export(IndexReportRequest $request, ReportService $reports, ReportCsvService $csv): StreamedResponse
    {
        return $csv->downloadAccesses($reports, $request->safe()->except(['accesses_page']));
    }

    public function spreadsheet(IndexReportRequest $request, ReportSpreadsheetService $spreadsheet): StreamedResponse
    {
        return $spreadsheet->download($request->user(), 'accesses', $request->safe()->except(['accesses_page']));
    }
}
