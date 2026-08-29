<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Seeder;

class VisitorAccessSeeder extends Seeder
{
    public function run(): void
    {
        $doorman = User::query()->where('role', UserRole::Porteiro)->first();
        $authorizations = VisitorAuthorization::query()->get();

        if (! $doorman || $authorizations->isEmpty()) {
            $this->command->warn('Crie um porteiro e autorizações antes de gerar acessos.');

            return;
        }

        $activeAuthorizations = VisitorAuthorization::query()
            ->where('status', VisitorAuthorizationStatus::Active)
            ->whereDoesntHave('visitorAccesses', fn ($query) => $query
                ->where('validation_status', 'validated')
                ->whereNull('exit_time'))
            ->take(3)
            ->get();

        foreach ($activeAuthorizations as $authorization) {
            VisitorAccess::factory()->open()->create([
                'visitor_authorization_id' => $authorization->id,
                'doorman_id' => $doorman->id,
            ]);
        }

        $remainingAccesses = max(0, 10 - VisitorAccess::query()->count());

        for ($index = 0; $index < $remainingAccesses; $index++) {
            $authorization = $authorizations[$index % $authorizations->count()];

            VisitorAccess::factory()->rejected()->create([
                'visitor_authorization_id' => $authorization->id,
                'doorman_id' => $doorman->id,
            ]);
        }
    }
}
