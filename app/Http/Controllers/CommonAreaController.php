<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommonAreaRequest;
use App\Http\Requests\UpdateCommonAreaRequest;
use App\Models\CommonArea;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CommonAreaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/common-areas', [
            'areas' => CommonArea::query()->orderBy('name')->orderBy('id')->paginate(15),
        ]);
    }

    public function store(StoreCommonAreaRequest $request): RedirectResponse
    {
        CommonArea::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Área cadastrada com sucesso.',
        ]);

        return back();
    }

    public function update(UpdateCommonAreaRequest $request, CommonArea $commonArea): RedirectResponse
    {
        $commonArea->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Área atualizada com sucesso.',
        ]);

        return back();
    }
}
