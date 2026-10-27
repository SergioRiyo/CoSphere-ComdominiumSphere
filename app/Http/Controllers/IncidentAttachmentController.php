<?php

namespace App\Http\Controllers;

use App\Models\IncidentAttachment;
use App\Services\IncidentAttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentAttachmentController extends Controller
{
    public function __invoke(Request $request, IncidentAttachment $incidentAttachment, IncidentAttachmentService $attachments): StreamedResponse
    {
        return $attachments->download($request->user(), $incidentAttachment);
    }
}
