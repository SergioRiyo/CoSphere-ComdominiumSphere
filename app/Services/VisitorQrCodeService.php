<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\VisitorAuthorization;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

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
        return $this->payload($authorization);
    }
}
