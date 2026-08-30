<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\Notification;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisitorService
{
    public function generateVisitorCode(): string
    {
        do {
            $code = 'csa_'.Str::random(32);
        } while (VisitorAuthorization::where('access_code', $code)->exists());

        return $code;
    }

    /**
     * @param  array{name: string, cpf: string, phone: string, vehicle_plate?: string|null, start_date: string, end_date: string}  $data
     */
    public function createDirectAuthorization(User $resident, array $data): VisitorAuthorization
    {
        return DB::transaction(function () use ($data, $resident) {
            $resident->loadMissing('unit');

            if ($resident->role !== UserRole::Morador
                || ! $resident->is_active
                || $resident->unit === null
                || $resident->unit->status !== 'active') {
                throw new DomainException('O morador precisa estar vinculado a uma unidade ativa.');
            }

            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            if ($startDate->isPast()) {
                throw new DomainException('O início da visita não pode estar no passado.');
            }

            if ($startDate->greaterThanOrEqualTo($endDate)) {
                throw new DomainException('O término deve ser posterior ao início da visita.');
            }

            $visitor = Visitor::withTrashed()
                ->where('cpf', $data['cpf'])
                ->first();

            if ($visitor === null) {
                $visitor = Visitor::create([
                    'cpf' => $data['cpf'],
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                ]);
            } elseif ($visitor->trashed()) {
                $visitor->restore();
            }

            $accessCode = $this->generateVisitorCode();

            $authorization = VisitorAuthorization::create([
                'visitor_id' => $visitor->id,
                'unit_id' => $resident->unit_id,
                'resident_id' => $resident->id,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => VisitorAuthorizationStatus::Active->value,
                'authorized_date' => now(),
                'access_code' => $accessCode,
            ]);

            $this->notifyResident(
                authorization: $authorization,
                title: 'Visitante autorizado',
                message: "A autorização de acesso para {$visitor->name} foi criada com sucesso."
            );

            return $authorization;
        });
    }

    public function validateAuthorizationByCode(string $accessCode): VisitorAuthorization
    {
        $authorization = VisitorAuthorization::where('access_code', $accessCode)->first();

        if (! $authorization) {
            throw new DomainException('A autorização não foi encontrada.');
        }

        return $this->validateAuthorization($authorization);
    }

    /**
     * @return array{
     *     allowed: bool,
     *     reason: string|null,
     *     message: string,
     *     authorization: array{
     *         visitor_name: string,
     *         unit: array{block: string|null, number: string},
     *         vehicle_plate: string|null,
     *         start_date: string,
     *         end_date: string,
     *         status: string
     *     }|null
     * }
     */
    public function validateAccessCode(string $accessCode): array
    {
        $authorization = VisitorAuthorization::query()
            ->where('access_code', $accessCode)
            ->first();

        if (! $authorization) {
            return $this->deniedValidationResult(
                reason: 'not_found',
                message: 'Código de acesso não encontrado.',
            );
        }

        $denial = $this->authorizationDenial($authorization);

        if ($denial !== null) {
            return $this->deniedValidationResult(
                reason: $denial['reason'],
                message: $denial['message'],
            );
        }

        return [
            'allowed' => true,
            'reason' => null,
            'message' => 'Acesso liberado.',
            'authorization' => [
                'visitor_name' => $authorization->visitor->name,
                'unit' => [
                    'block' => $authorization->unit->block,
                    'number' => $authorization->unit->number,
                ],
                'vehicle_plate' => $authorization->vehicle_plate,
                'start_date' => $authorization->start_date->toIso8601String(),
                'end_date' => $authorization->end_date->toIso8601String(),
                'status' => $authorization->status->value,
            ],
        ];
    }

    public function validateAuthorization(VisitorAuthorization $authorization): VisitorAuthorization
    {
        $denial = $this->authorizationDenial($authorization);

        if ($denial !== null) {
            throw new DomainException($denial['message']);
        }

        return $authorization;
    }

    public function registerEntry(
        string $accessCode,
        int $doormanId,
        ?string $observations = null
    ): VisitorAccess {
        $authorization = VisitorAuthorization::where('access_code', $accessCode)->first();

        if (! $authorization) {
            throw new DomainException('Código de acesso inválido.');
        }

        try {
            $this->validateAuthorization($authorization);
        } catch (DomainException $exception) {
            $this->denyAccess(
                authorization: $authorization,
                doormanId: $doormanId,
                reason: $exception->getMessage(),
            );

            throw $exception;
        }

        return DB::transaction(function () use ($authorization, $doormanId, $observations) {
            $authorization = VisitorAuthorization::whereKey($authorization->id)
                ->lockForUpdate()
                ->first();

            if (! $authorization) {
                throw new DomainException('Código de acesso inválido.');
            }

            $this->validateAuthorization($authorization);

            $access = VisitorAccess::create([
                'visitor_authorization_id' => $authorization->id,
                'doorman_id' => $doormanId,
                'entry_time' => now(),
                'exit_time' => null,
                'validation_status' => VisitorAccessStatus::Validated,
                'observations' => $observations,
            ]);
            $authorization->loadMissing('visitor');

            $this->notifyResident(
                authorization: $authorization,
                title: 'Entrada de visitante registrada',
                message: "O visitante {$authorization->visitor->name} teve a entrada registrada na portaria."
            );

            return $access;
        });
    }

    public function registerExit(
        string $accessCode,
        int $doormanId,
        ?string $observations = null
    ): VisitorAccess {
        return DB::transaction(function () use ($accessCode, $doormanId, $observations) {
            $authorization = VisitorAuthorization::where('access_code', $accessCode)
                ->lockForUpdate()
                ->first();

            if (! $authorization) {
                throw new DomainException('Código de acesso inválido.');
            }

            $access = VisitorAccess::where('visitor_authorization_id', $authorization->id)
                ->where('validation_status', VisitorAccessStatus::Validated)
                ->whereNull('exit_time')
                ->latest('entry_time')
                ->first();

            if (! $access) {
                throw new DomainException('Não existe entrada em aberto para este visitante.');
            }

            $access->update([
                'exit_doorman_id' => $doormanId,
                'exit_time' => now(),
                'observations' => $observations ?? $access->observations,
            ]);

            $authorization->update([
                'status' => VisitorAuthorizationStatus::Used,
            ]);

            $authorization->loadMissing('visitor');

            $this->notifyResident(
                authorization: $authorization,
                title: 'Saída de visitante registrada',
                message: "O visitante {$authorization->visitor->name} teve a saída registrada na portaria."
            );

            return $access->refresh();
        });
    }

    public function denyAccess(
        VisitorAuthorization $authorization,
        int $doormanId,
        ?string $reason = null
    ): VisitorAccess {
        $access = VisitorAccess::create([
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $doormanId,
            'entry_time' => null,
            'exit_time' => null,
            'validation_status' => VisitorAccessStatus::Rejected,
            'observations' => $reason,
        ]);

        $authorization->loadMissing('visitor');

        $visitorName = $authorization->visitor?->name ?? 'visitante';

        $this->notifyResident(
            authorization: $authorization,
            title: 'Acesso de visitante negado',
            message: "O acesso do visitante {$visitorName} foi negado. Motivo: {$reason}"
        );

        return $access;
    }

    private function notifyResident(
        VisitorAuthorization $authorization,
        string $title,
        string $message
    ): Notification {
        return Notification::create([
            'recipient_id' => $authorization->resident_id,
            'title' => $title,
            'message' => $message,
            'type' => NotificationType::Visitor,
            'sent_at' => now(),
            'is_read' => false,
        ]);
    }

    /**
     * @return array{reason: string, message: string}|null
     */
    private function authorizationDenial(VisitorAuthorization $authorization): ?array
    {
        if ($authorization->status === VisitorAuthorizationStatus::Canceled) {
            return $this->denial('canceled', 'Autorização cancelada.');
        }

        if ($authorization->status === VisitorAuthorizationStatus::Expired) {
            return $this->denial('expired', 'Autorização expirada.');
        }

        if ($authorization->status === VisitorAuthorizationStatus::Used) {
            return $this->denial('used', 'Autorização já utilizada.');
        }

        if ($authorization->status === VisitorAuthorizationStatus::PendingData) {
            return $this->denial('pending_data', 'Autorização aguardando preenchimento de dados.');
        }

        if (! $authorization->visitor_id || ! $authorization->access_code) {
            return $this->denial(
                'inconsistent_authorization',
                'Autorização sem os dados obrigatórios do visitante.',
            );
        }

        $now = now();

        if ($authorization->end_date && $now->greaterThan($authorization->end_date)) {
            $authorization->update([
                'status' => VisitorAuthorizationStatus::Expired,
            ]);

            return $this->denial('expired', 'Autorização expirada.');
        }

        if ($authorization->start_date && $now->lessThan($authorization->start_date)) {
            return $this->denial('future', 'Autorização ainda não está ativa.');
        }

        if ($authorization->status !== VisitorAuthorizationStatus::Active) {
            return $this->denial('inconsistent_authorization', 'Autorização inválida.');
        }

        $authorization->loadMissing([
            'visitor:id,name',
            'resident:id,unit_id,role,is_active',
            'unit:id,block,number,status',
        ]);

        if ($authorization->visitor === null
            || $authorization->resident === null
            || $authorization->unit === null
            || ! $authorization->resident->is_active
            || $authorization->resident->role !== UserRole::Morador
            || $authorization->resident->unit_id !== $authorization->unit_id
            || $authorization->unit->status !== 'active') {
            return $this->denial(
                'inconsistent_authorization',
                'Os dados da autorização estão inconsistentes.',
            );
        }

        $hasOpenAccess = $authorization->visitorAccesses()
            ->where('validation_status', VisitorAccessStatus::Validated->value)
            ->whereNull('exit_time')
            ->exists();

        if ($hasOpenAccess) {
            return $this->denial(
                'open_access',
                'Este visitante já possui uma entrada registrada sem saída.',
            );
        }

        return null;
    }

    /**
     * @return array{reason: string, message: string}
     */
    private function denial(string $reason, string $message): array
    {
        return [
            'reason' => $reason,
            'message' => $message,
        ];
    }

    /**
     * @return array{allowed: false, reason: string, message: string, authorization: null}
     */
    private function deniedValidationResult(string $reason, string $message): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'message' => $message,
            'authorization' => null,
        ];
    }
}
