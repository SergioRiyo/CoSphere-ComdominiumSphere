<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\AnnouncementPublishedMail;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAnnouncementEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $announcementId, public int $residentId) {}

    public function handle(): void
    {
        $announcement = Announcement::query()->whereNotNull('published_at')->find($this->announcementId);
        $resident = User::query()->where('role', UserRole::Morador)->where('is_active', true)
            ->whereNotNull('email_verified_at')->find($this->residentId);

        if ($announcement === null || $resident === null) {
            return;
        }

        Mail::to($resident->email)->send(new AnnouncementPublishedMail($announcement));
    }
}
