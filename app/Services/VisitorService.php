<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\Notification;
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
            $code = 'VIS_' . Str::upper(Str::random(8));
        } while (VisitorAuthorization::where('access_code', $code)->exists());

        return $code;
    }

    public function createVisitorAuthorization(array $data): VisitorAuthorization
    {
        return DB::transaction(function () use ($data) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            if ($startDate->greaterThanOrEqualTo($endDate)) {
                throw new DomainException('A data de início deve ser anterior à data de término.');
            }

            $visitor = Visitor::updateOrCreate(
                [
                    'cpf' => $data['cpf']
                ],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null
                ]
            );

            $accessCode = $this->generateVisitorCode();

            $authorization = VisitorAuthorization::create([
                'visitor_id' => $visitor->id,
                'unit_id' => $data['unit_id'],
                'resident_id' => $data['resident_id'],
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
                'qr_code' => $accessCode,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => VisitorAuthorizationStatus::Active->value,
                'registration_link' => $data['registration_link'] ?? null,
                'authorization_date' => now(),
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

    public function validateAuthorization(VisitorAuthorization $authorization): VisitorAuthorization
    {
        $now = now();

        if ($authorization->status === VisitorAuthorizationStatus::Canceled) {
            throw new DomainException('Autorização cancelada.');
        }

        if ($authorization->status === VisitorAuthorizationStatus::Expired) {
            throw new DomainException('Autorização expirada.');
        }

        if ($authorization->status === VisitorAuthorizationStatus::Used) {
            throw new DomainException('Autorização já utilizada.');
        }

        if ($authorization->status === VisitorAuthorizationStatus::PendingData) {
            throw new DomainException('Autorização aguardando preenchimento de dados.');
        }

        if ($authorization->end_date && $now->greaterThan($authorization->end_date)) {
            $authorization->update([
                'status' => VisitorAuthorizationStatus::Expired,
            ]);

            throw new DomainException('Autorização expirada.');
        }

        if ($authorization->start_date && $now->lessThan($authorization->start_date)) {
            throw new DomainException('Autorização ainda não está ativa.');
        }

        if ($authorization->status !== VisitorAuthorizationStatus::Active) {
            throw new DomainException('Autorização inválida.');
        }

        return $authorization;
    }

    public function registerEntry(
        string $accessCode,
        int $doormanId,
        ?string $observations = null
    ): VisitorAccess {
        return DB::transaction(function () use ($accessCode, $doormanId, $observations) {
            $authorization = VisitorAuthorization::where('access_code', $accessCode)->lockForUpdate()->first();

            if (! $authorization) {
                throw new DomainException('Código de acesso inválido.');
            }

            try {
                $this->validateAuthorization($authorization);
            } catch (DomainException $exception) {
                $this->denyAccess(
                    authorization: $authorization,
                    doormanId: $doormanId,
                    reason: $exception->getMessage()
                );

                throw $exception;
            }

            $hasOpenAccess = VisitorAccess::where('visitor_authorization_id', $authorization->id)
                ->where('validation_status', VisitorAccessStatus::Validated->value)
                ->whereNull('exit_time')
                ->exists();

            if ($hasOpenAccess) {
                throw new DomainException('Este visitante já possui uma entrada registrada sem saída.');
            }

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
                'doorman_id' => $doormanId,
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
}
