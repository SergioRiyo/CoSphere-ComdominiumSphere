<?php

namespace Tests\Feature;

use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use App\Services\VisitorAccessQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortariaVisitorPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_doorman_receives_only_open_accesses_in_descending_order_with_sanitized_data(): void
    {
        $unit = Unit::factory()->create([
            'block' => 'A',
            'number' => '102',
            'status' => 'active',
        ]);
        $resident = User::factory()->morador()->create(['unit_id' => $unit->id]);
        $visitor = Visitor::factory()->create([
            'name' => 'João Visitante',
            'cpf' => '529.982.247-25',
            'phone' => '(65) 99999-9999',
        ]);
        $entryDoorman = User::factory()->porteiro()->create([
            'name' => 'Carlos da Portaria',
        ]);
        $authorization = VisitorAuthorization::factory()->active()->create([
            'visitor_id' => $visitor->id,
            'unit_id' => $unit->id,
            'resident_id' => $resident->id,
            'vehicle_plate' => 'ABC1D23',
            'access_code' => 'csa_lista_dado_sensivel',
            'invitation_token_hash' => hash('sha256', 'convite-lista-secreto'),
        ]);
        $recentAccess = VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $entryDoorman->id,
            'entry_time' => now()->subMinutes(10),
        ]);

        $olderAccess = VisitorAccess::factory()->open()->create([
            'entry_time' => now()->subHours(2),
        ]);

        VisitorAccess::factory()->closed()->create();
        VisitorAccess::factory()->pending()->create();
        VisitorAccess::factory()->rejected()->create();
        VisitorAccess::factory()->create([
            'entry_time' => null,
            'exit_time' => null,
            'validation_status' => VisitorAccessStatus::Validated,
        ]);

        $response = $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.visitor-accesses.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('portaria/visitor-accesses/index')
            ->where('timezone', config('app.timezone'))
            ->has('openAccesses', 2)
            ->has('openAccesses.0', 6)
            ->where('openAccesses.0.id', $recentAccess->id)
            ->where('openAccesses.0.visitor_name', 'João Visitante')
            ->where('openAccesses.0.unit', [
                'block' => 'A',
                'number' => '102',
            ])
            ->where('openAccesses.0.vehicle_plate', 'ABC1D23')
            ->where('openAccesses.0.entry_time', $recentAccess->entry_time->toIso8601String())
            ->where('openAccesses.0.entry_doorman_name', 'Carlos da Portaria')
            ->where('openAccesses.1.id', $olderAccess->id)
            ->missing('openAccesses.0.cpf')
            ->missing('openAccesses.0.phone')
            ->missing('openAccesses.0.access_code')
            ->missing('openAccesses.0.invitation_token_hash')
            ->missing('openAccesses.0.visitor_authorization_id')
            ->missing('openAccesses.0.doorman_id')
            ->missing('openAccesses.0.exit_doorman_id')
            ->missing('openAccesses.0.resident_id'));

        $serializedResponse = $response->getContent();

        $this->assertStringNotContainsString($visitor->cpf, $serializedResponse);
        $this->assertStringNotContainsString($visitor->phone, $serializedResponse);
        $this->assertStringNotContainsString($authorization->access_code, $serializedResponse);
        $this->assertStringNotContainsString($authorization->invitation_token_hash, $serializedResponse);
    }

    public function test_list_returns_an_empty_array_when_there_are_no_open_accesses(): void
    {
        VisitorAccess::factory()->closed()->create();
        VisitorAccess::factory()->pending()->create();
        VisitorAccess::factory()->rejected()->create();
        VisitorAccess::factory()->create([
            'entry_time' => null,
            'exit_time' => null,
            'validation_status' => VisitorAccessStatus::Validated,
        ]);

        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.visitor-accesses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('portaria/visitor-accesses/index')
                ->has('openAccesses', 0));
    }

    public function test_open_access_remains_present_regardless_of_authorization_status(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();
        $access = VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
        ]);

        $authorization->update([
            'status' => VisitorAuthorizationStatus::Expired,
        ]);

        $this->actingAs(User::factory()->porteiro()->create())
            ->get(route('portaria.visitor-accesses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('openAccesses', 1)
                ->where('openAccesses.0.id', $access->id));
    }

    public function test_only_active_verified_doormen_can_access_the_presence_list(): void
    {
        $route = route('portaria.visitor-accesses.index');

        $this->get($route)->assertRedirect(route('login'));

        foreach ([
            User::factory()->admin()->create(),
            User::factory()->morador()->create(),
        ] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get($route)
                ->assertForbidden();
        }

        $inactiveDoorman = User::factory()->porteiro()->inactive()->create();

        $this->actingAs($inactiveDoorman)
            ->get($route)
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->actingAs(User::factory()->porteiro()->unverified()->create())
            ->get($route)
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->porteiro()->create())
            ->get($route)
            ->assertInertia(fn (Assert $page) => $page
                ->component('portaria/visitor-accesses/index'));
    }

    public function test_open_access_query_does_not_introduce_n_plus_one_queries(): void
    {
        VisitorAccess::factory()->open()->create();
        $service = app(VisitorAccessQueryService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $service->openForPortaria();
            $singleAccessQueryCount = count(DB::getQueryLog());

            VisitorAccess::factory()->open()->count(9)->create();

            DB::flushQueryLog();
            $service->openForPortaria();
            $multipleAccessQueryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame($singleAccessQueryCount, $multipleAccessQueryCount);
        $this->assertLessThanOrEqual(5, $multipleAccessQueryCount);
    }
}
