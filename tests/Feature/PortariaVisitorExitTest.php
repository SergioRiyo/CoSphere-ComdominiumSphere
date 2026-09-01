<?php

namespace Tests\Feature;

use App\Enums\VisitorAccessStatus;
use App\Enums\VisitorAuthorizationStatus;
use App\Models\User;
use App\Models\VisitorAccess;
use App\Models\VisitorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortariaVisitorExitTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_doorman_registers_exit_without_overwriting_entry_operator_or_trusting_client_ids(): void
    {
        $entryDoorman = User::factory()->porteiro()->create();
        $exitDoorman = User::factory()->porteiro()->create();
        $clientSelectedDoorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->active()->create();
        $access = VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $entryDoorman->id,
        ]);
        $otherAccess = VisitorAccess::factory()->open()->create();

        $this->actingAs($exitDoorman)
            ->from(route('portaria.visitor-accesses.index'))
            ->post(route('portaria.visitor-accesses.exit', $access), [
                'visitor_access_id' => $otherAccess->id,
                'doorman_id' => $clientSelectedDoorman->id,
                'exit_doorman_id' => $clientSelectedDoorman->id,
                'user_id' => $clientSelectedDoorman->id,
            ])
            ->assertRedirect(route('portaria.visitor-accesses.index'))
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'Saída registrada com sucesso.',
            ]);

        $access->refresh();
        $authorization->refresh();
        $otherAccess->refresh();

        $this->assertNotNull($access->exit_time);
        $this->assertSame($entryDoorman->id, $access->doorman_id);
        $this->assertSame($exitDoorman->id, $access->exit_doorman_id);
        $this->assertSame(VisitorAuthorizationStatus::Used, $authorization->status);
        $this->assertNull($otherAccess->exit_time);
        $this->assertNull($otherAccess->exit_doorman_id);
    }

    public function test_only_active_verified_doormen_can_register_an_exit(): void
    {
        $access = VisitorAccess::factory()->open()->create();
        $route = route('portaria.visitor-accesses.exit', $access);

        $this->post($route)->assertRedirect(route('login'));

        foreach ([
            User::factory()->admin()->create(),
            User::factory()->morador()->create(),
        ] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->post($route)
                ->assertForbidden();
        }

        $inactiveDoorman = User::factory()->porteiro()->inactive()->create();

        $this->actingAs($inactiveDoorman)
            ->post($route)
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->actingAs(User::factory()->porteiro()->unverified()->create())
            ->post($route)
            ->assertRedirect(route('verification.notice'));

        $access->refresh();

        $this->assertNull($access->exit_time);
        $this->assertNull($access->exit_doorman_id);
        $this->assertSame(
            VisitorAuthorizationStatus::Active,
            $access->visitorAuthorization->status,
        );
    }

    public function test_exit_is_rejected_when_access_has_no_registered_entry(): void
    {
        $authorization = VisitorAuthorization::factory()->active()->create();
        $accessWithoutEntry = VisitorAccess::factory()->create([
            'visitor_authorization_id' => $authorization->id,
            'entry_time' => null,
            'exit_time' => null,
            'validation_status' => VisitorAccessStatus::Validated,
        ]);

        $this->actingAs(User::factory()->porteiro()->create())
            ->from(route('portaria.visitor-accesses.index'))
            ->post(route('portaria.visitor-accesses.exit', $accessWithoutEntry))
            ->assertRedirect(route('portaria.visitor-accesses.index'))
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message');

        $accessWithoutEntry->refresh();

        $this->assertNull($accessWithoutEntry->exit_time);
        $this->assertNull($accessWithoutEntry->exit_doorman_id);
        $this->assertSame(VisitorAuthorizationStatus::Active, $authorization->refresh()->status);
    }

    public function test_repeated_exit_request_is_controlled_and_does_not_change_the_first_exit(): void
    {
        $entryDoorman = User::factory()->porteiro()->create();
        $firstExitDoorman = User::factory()->porteiro()->create();
        $secondExitDoorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->active()->create();
        $access = VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
            'doorman_id' => $entryDoorman->id,
        ]);
        $route = route('portaria.visitor-accesses.exit', $access);
        $returnRoute = route('portaria.visitor-accesses.index');

        $this->actingAs($firstExitDoorman)
            ->from($returnRoute)
            ->post($route)
            ->assertRedirect($returnRoute);

        $access->refresh();
        $firstExitTime = $access->exit_time;

        $this->actingAs($secondExitDoorman)
            ->from($returnRoute)
            ->post($route)
            ->assertRedirect($returnRoute)
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message');

        $access->refresh();

        $this->assertTrue($firstExitTime->equalTo($access->exit_time));
        $this->assertSame($entryDoorman->id, $access->doorman_id);
        $this->assertSame($firstExitDoorman->id, $access->exit_doorman_id);
        $this->assertSame(VisitorAuthorizationStatus::Used, $authorization->refresh()->status);
        $this->assertSame(1, $authorization->visitorAccesses()->count());
    }

    public function test_after_exit_access_leaves_presence_list_and_code_is_rejected_as_used(): void
    {
        $doorman = User::factory()->porteiro()->create();
        $authorization = VisitorAuthorization::factory()->active()->create();
        $access = VisitorAccess::factory()->open()->create([
            'visitor_authorization_id' => $authorization->id,
        ]);

        $this->actingAs($doorman)
            ->from(route('portaria.visitor-accesses.index'))
            ->post(route('portaria.visitor-accesses.exit', $access))
            ->assertRedirect(route('portaria.visitor-accesses.index'));

        $this->get(route('portaria.visitor-accesses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('openAccesses', 0));

        $this->postJson(route('portaria.visitor-authorizations.validate'), [
            'access_code' => $authorization->access_code,
        ])
            ->assertOk()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('reason', 'used')
            ->assertJsonPath('authorization', null);

        $this->assertSame(VisitorAuthorizationStatus::Used, $authorization->refresh()->status);
    }
}
