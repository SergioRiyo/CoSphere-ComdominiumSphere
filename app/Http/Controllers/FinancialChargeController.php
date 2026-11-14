<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeFinancialChargeRequest;
use App\Http\Requests\IndexFinancialChargeRequest;
use App\Http\Requests\StoreFinancialChargeRequest;
use App\Models\FinancialCharge;
use App\Services\FinancialChargeService;
use App\Services\SimulatedBoletoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FinancialChargeController extends Controller
{
    public function index(IndexFinancialChargeRequest $request, FinancialChargeService $service): Response
    {
        return Inertia::render('financial/index', $service->index($request->user(), $request->validated()));
    }

    public function show(Request $request, FinancialCharge $financialCharge, FinancialChargeService $service): Response
    {
        return Inertia::render('financial/show', ['charge' => $service->detail($request->user(), $financialCharge)]);
    }

    public function store(StoreFinancialChargeRequest $request, FinancialChargeService $service): RedirectResponse
    {
        $service->create($request->user(), $request->validated());

        return back()->with('success', 'Cobrança simulada cadastrada.');
    }

    public function update(StoreFinancialChargeRequest $request, FinancialCharge $financialCharge, FinancialChargeService $service): RedirectResponse
    {
        $service->update($request->user(), $financialCharge, $request->validated());

        return back()->with('success', 'Cobrança atualizada.');
    }

    public function change(ChangeFinancialChargeRequest $request, FinancialCharge $financialCharge, FinancialChargeService $service): RedirectResponse
    {
        $service->change($request->user(), $financialCharge, $request->validated());

        return back()->with('success', 'Cobrança atualizada.');
    }

    public function boleto(Request $request, FinancialCharge $financialCharge, FinancialChargeService $service, SimulatedBoletoService $boleto): HttpResponse
    {
        $service->authorizeCharge($request->user(), $financialCharge);

        return $boleto->download($financialCharge);
    }
}
