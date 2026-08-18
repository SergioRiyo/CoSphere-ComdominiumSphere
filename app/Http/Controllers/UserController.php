<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private UserManagementService $userManagementService) {}

    public function index(IndexUserRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('admin/users', [
            'users' => $this->userManagementService->paginate($filters),
            'units' => $this->userManagementService->unitOptions(),
            'roleOptions' => array_map(
                static fn (UserRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                UserRole::cases(),
            ),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userManagementService->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Usuário cadastrado com sucesso.',
        ]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userManagementService->update($user, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Usuário atualizado com sucesso.',
        ]);

        return back();
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $isActive = $request->boolean('is_active');

        $this->userManagementService->updateStatus($user, $isActive);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive
                ? 'Usuário ativado com sucesso.'
                : 'Usuário inativado com sucesso.',
        ]);

        return back();
    }
}
