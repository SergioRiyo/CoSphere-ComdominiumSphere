<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAuthorization;
use Illuminate\Database\Seeder;

class VisitorAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        if (VisitorAuthorization::query()->exists()) {
            return;
        }

        $resident = User::query()
            ->where('role', UserRole::Morador)
            ->whereNotNull('unit_id')
            ->first();
        $visitor = Visitor::query()->first();

        if (! $resident || ! $visitor) {
            $this->command->warn('Crie um morador vinculado a uma unidade e visitantes antes das autorizações.');

            return;
        }

        $ownership = [
            'unit_id' => $resident->unit_id,
            'resident_id' => $resident->id,
        ];
        $identifiedVisitor = $ownership + ['visitor_id' => $visitor->id];

        VisitorAuthorization::factory()->pendingData()->create($ownership);
        VisitorAuthorization::factory()->pendingData()->create($ownership);
        VisitorAuthorization::factory()->active()->count(4)->create($identifiedVisitor);
        VisitorAuthorization::factory()->future()->create($identifiedVisitor);
        VisitorAuthorization::factory()->expired()->create($identifiedVisitor);
        VisitorAuthorization::factory()->canceled()->create($identifiedVisitor);
        VisitorAuthorization::factory()->used()->create($identifiedVisitor);
    }
}
