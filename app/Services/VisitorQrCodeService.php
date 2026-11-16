<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\VisitorAuthorization;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class VisitorQrCodeService
{
    public function isAvailable(VisitorAuthorization $authorization): bool
    {
        $authorization->loadMissing([
            'resident:id,unit_id,role,is_active',
            'unit:id,status',
        ]);

        return $authorization->status === VisitorAuthorizationStatus::Active
            && $authorization->visitor_id !== null
            && $authorization->access_code !== null
            && $authorization->end_date->isFuture()
            && $authorization->resident !== null
            && $authorization->resident->role === UserRole::Morador
            && $authorization->resident->is_active
            && $authorization->resident->unit_id === $authorization->unit_id
            && $authorization->unit !== null
            && $authorization->unit->status === 'active';
    }

    public function svg(VisitorAuthorization $authorization): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(320),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->payload($authorization));
    }

    public function payload(VisitorAuthorization $authorization): string
    {
        if (! $this->isAvailable($authorization)) {
            abort(404);
        }

        return $authorization->access_code;
    }

    public function manualCode(VisitorAuthorization $authorization): string
    {
        $accessCode = $this->payload($authorization);

        if (! str_starts_with($accessCode, 'csa_')) {
            return $accessCode;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($authorization): string {
                    $current = VisitorAuthorization::query()->lockForUpdate()->findOrFail($authorization->id);
                    $this->payload($current);
                    if ($current->manual_code === null) {
                        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                        $code = 'M';
                        for ($index = 0; $index < 12; $index++) {
                            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                        }
                        $current->forceFill(['manual_code' => $code])->saveOrFail();
                    }

                    return $current->manual_code;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Não foi possível gerar o código manual.');
    }
}
