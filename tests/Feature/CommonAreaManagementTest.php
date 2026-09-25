<?php

namespace Tests\Feature;

use App\Models\CommonArea;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommonAreaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_areas_and_an_empty_list(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.common-areas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/common-areas')->has('areas.data', 0));

        $area = CommonArea::factory()->create();
        $this->get(route('admin.common-areas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('areas.data', 1)
                ->where('areas.data.0.id', $area->id)
                ->where('areas.data.0.name', $area->name)
                ->where('areas.data.0.requires_approval', true));
    }

    public function test_listing_is_paginated_on_the_server(): void
    {
        CommonArea::factory()->count(16)->create();
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.common-areas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('areas.data', 15)->where('areas.total', 16)->where('areas.last_page', 2));

        $this->get(route('admin.common-areas.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('areas.data', 1)->where('areas.current_page', 2));
    }

    public function test_admin_can_create_manual_and_automatic_areas(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['Salão de Festas' => true, 'Churrasqueira' => false] as $name => $approval) {
            $data = $this->validData(['name' => $name, 'requires_approval' => $approval]);
            $this->from(route('admin.common-areas.index'))
                ->post(route('admin.common-areas.store'), $data)
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.common-areas.index'));
            $this->assertDatabaseHas('common_areas', $data);
            $this->assertSame($approval, CommonArea::where('name', $name)->firstOrFail()->requires_approval);
        }
    }

    public function test_nullable_description_and_schedule_can_be_left_empty(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.common-areas.store'), $this->validData([
                'description' => '',
                'available_from' => '',
                'available_until' => '',
            ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('common_areas', [
            'name' => 'Salão',
            'description' => null,
            'available_from' => null,
            'available_until' => null,
        ]);
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_data_is_rejected_on_create_and_update(string $field, mixed $value): void
    {
        $area = CommonArea::factory()->create();
        $original = $area->refresh()->getRawOriginal();
        $this->actingAs(User::factory()->admin()->create());
        $data = $this->validData([$field => $value]);

        $this->post(route('admin.common-areas.store'), $data)->assertSessionHasErrors($field);
        $this->patch(route('admin.common-areas.update', $area), $data)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('common_areas', 1);
        $this->assertSame($original, $area->refresh()->getRawOriginal());
    }

    public static function invalidFields(): array
    {
        return [
            'name missing' => ['name', null],
            'name blank' => ['name', '   '],
            'name too long' => ['name', str_repeat('a', 256)],
            'description not text' => ['description', ['invalid']],
            'opening malformed' => ['available_from', '25:00'],
            'closing malformed' => ['available_until', 'not a time'],
            'opening missing' => ['available_from', null],
            'closing missing' => ['available_until', null],
            'equal hours' => ['available_until', '08:00'],
            'overnight' => ['available_until', '06:00'],
            'duration missing' => ['max_reservation_minutes', null],
            'duration zero' => ['max_reservation_minutes', 0],
            'duration negative' => ['max_reservation_minutes', -1],
            'duration fractional' => ['max_reservation_minutes', 1.5],
            'duration exceeds postgres smallint' => ['max_reservation_minutes', 32768],
            'rules missing' => ['rules', null],
            'rules blank' => ['rules', '   '],
            'rules not text' => ['rules', ['invalid']],
            'approval missing' => ['requires_approval', null],
            'approval invalid' => ['requires_approval', 'automatic'],
            'status missing' => ['status', null],
            'status invalid' => ['status', 'deleted'],
        ];
    }

    public function test_required_fields_cannot_be_omitted(): void
    {
        $area = CommonArea::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        foreach (['post' => route('admin.common-areas.store'), 'patch' => route('admin.common-areas.update', $area)] as $method => $url) {
            $this->{$method}($url, [])->assertSessionHasErrors([
                'name', 'max_reservation_minutes', 'rules', 'requires_approval', 'status',
            ]);
        }
    }

    public function test_names_are_unique_but_update_ignores_the_current_area(): void
    {
        $area = CommonArea::factory()->create(['name' => 'Piscina']);
        $other = CommonArea::factory()->create(['name' => 'Academia']);
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.common-areas.store'), $this->validData(['name' => ' Piscina ']))
            ->assertSessionHasErrors('name');
        $this->patch(route('admin.common-areas.update', $area), $this->validData(['name' => $other->name]))
            ->assertSessionHasErrors('name');
        $this->patch(route('admin.common-areas.update', $area), $this->validData(['name' => $area->name]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Piscina', $area->refresh()->name);
        $this->assertSame('Academia', $other->refresh()->name);
    }

    public function test_admin_can_edit_all_fields_without_changing_any_reservation(): void
    {
        $area = CommonArea::factory()->create(['maintenance_reason' => 'Motivo já registrado']);
        $reservation = Reservation::factory()->create(['common_area_id' => $area->id]);
        $originalReservation = $reservation->refresh()->getRawOriginal();
        $this->actingAs(User::factory()->admin()->create());

        foreach (['inactive', 'maintenance', 'active'] as $status) {
            $data = $this->validData([
                'name' => 'Área atualizada',
                'description' => 'Descrição atualizada',
                'available_from' => '09:00',
                'available_until' => '17:00',
                'max_reservation_minutes' => 120,
                'rules' => 'Regras atualizadas',
                'requires_approval' => $status === 'active',
                'status' => $status,
            ]);
            $this->from(route('admin.common-areas.index'))
                ->patch(route('admin.common-areas.update', $area), $data + [
                    'id' => 999999,
                    'maintenance_reason' => 'Campo não permitido',
                    'created_at' => '2000-01-01',
                    'reservations' => [],
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.common-areas.index'));

            $this->assertDatabaseHas('common_areas', ['id' => $area->id] + $data);
            $this->assertSame('Motivo já registrado', $area->refresh()->maintenance_reason);
            $this->assertSame($originalReservation, $reservation->refresh()->getRawOriginal());
            $this->assertDatabaseCount('reservations', 1);
        }
    }

    #[DataProvider('blockedUsers')]
    public function test_all_management_actions_are_protected(string $actor, string $expected): void
    {
        $area = CommonArea::factory()->create();
        $original = $area->refresh()->getRawOriginal();
        $user = match ($actor) {
            'morador' => User::factory()->morador()->create(),
            'porteiro' => User::factory()->porteiro()->create(),
            'inactive' => User::factory()->admin()->inactive()->create(),
            'unverified' => User::factory()->admin()->unverified()->create(),
            default => null,
        };

        foreach ([
            'get' => route('admin.common-areas.index'),
            'post' => route('admin.common-areas.store'),
            'patch' => route('admin.common-areas.update', $area),
        ] as $method => $url) {
            if ($user !== null) {
                $this->actingAs($user);
            }
            $response = $method === 'get'
                ? $this->get($url)
                : $this->{$method}($url, $this->validData());

            if ($expected === 'forbidden') {
                $response->assertForbidden();
            } else {
                $response->assertRedirect(route($expected));
            }
        }

        $this->assertDatabaseCount('common_areas', 1);
        $this->assertSame($original, $area->refresh()->getRawOriginal());
    }

    public static function blockedUsers(): array
    {
        return [
            'guest' => ['guest', 'login'],
            'resident' => ['morador', 'forbidden'],
            'doorman' => ['porteiro', 'forbidden'],
            'inactive admin' => ['inactive', 'login'],
            'unverified admin' => ['unverified', 'verification.notice'],
        ];
    }

    /** @return array<string, mixed> */
    private function validData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Salão',
            'description' => 'Espaço para eventos',
            'available_from' => '08:00',
            'available_until' => '22:00',
            'max_reservation_minutes' => 240,
            'rules' => 'Respeitar o horário de funcionamento.',
            'requires_approval' => true,
            'status' => 'active',
        ], $overrides);
    }
}
