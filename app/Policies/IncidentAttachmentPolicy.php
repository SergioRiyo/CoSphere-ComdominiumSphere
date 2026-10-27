<?php

namespace App\Policies;

use App\Models\IncidentAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class IncidentAttachmentPolicy
{
    public function view(User $user, IncidentAttachment $attachment): bool
    {
        $incident = $attachment->incident;

        return $incident !== null && Gate::forUser($user)->allows('view', $incident);
    }
}
