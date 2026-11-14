<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\CommonArea;
use App\Models\CommonAreaBlock;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReservationCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_sees_occupied_periods_without_other_residents_details(): void
    {
        $resident = User::factory()->morador()->create();
        $area = CommonArea::factory()->create();
        $other = Reservation::factory()->create(['common_area_id' => $area->id, 'starts_at' => '2026-09-25 10:00', 'ends_at' => '2026-09-25 11:00']);
        Reservation::factory()->create(['common_area_id' => $area->id, 'starts_at' => '2026-09-25 12:00', 'ends_at' => '2026-09-25 13:00', 'status' => ReservationStatus::Cancelled]);
        CommonAreaBlock::factory()->create(['common_area_id' => $area->id, 'starts_at' => '2026-09-25 14:00', 'ends_at' => '2026-09-25 15:00']);

        $this->actingAs($resident)->getJson(route('morador.reservations.calendar', ['from' => '2026-09-01', 'to' => '2026-10-01', 'area' => $area->id]))
            ->assertOk()->assertJsonCount(1, 'reservations')->assertJsonCount(1, 'blocks')
            ->assertJsonPath('reservations.0.id', null)->assertJsonPath('reservations.0.status_label', 'Ocupado')
            ->assertJsonMissingPath('reservations.0.resident')->assertDontSee($other->user->name)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_admin_can_view_details_and_calendar_requires_valid_bounded_range(): void
    {
        $reservation = Reservation::factory()->create(['starts_at' => '2026-09-25 10:00', 'ends_at' => '2026-09-25 11:00']);
        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.reservations.calendar', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()->assertJsonPath('reservations.0.id', $reservation->id)
            ->assertJsonPath('reservations.0.resident', $reservation->user->name);
        $this->getJson(route('admin.reservations.calendar', ['from' => '2026-01-01', 'to' => '2026-12-31']))->assertUnprocessable();
        $this->actingAs(User::factory()->morador()->create())->getJson(route('admin.reservations.calendar', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertForbidden();
    }

    public function test_resident_can_see_an_area_in_maintenance_but_not_an_inactive_area(): void
    {
        $maintenance = CommonArea::factory()->create(['status' => 'maintenance']);
        $inactive = CommonArea::factory()->create(['status' => 'inactive']);
        $this->actingAs(User::factory()->morador()->create())
            ->getJson(route('morador.reservations.calendar', ['from' => '2026-09-01', 'to' => '2026-09-30', 'area' => $maintenance->id]))
            ->assertOk()->assertJsonFragment(['id' => $maintenance->id, 'status' => 'maintenance']);
        $this->getJson(route('morador.reservations.calendar', ['from' => '2026-09-01', 'to' => '2026-09-30', 'area' => $inactive->id]))->assertNotFound();
    }

    public function test_management_is_a_separate_filtered_page_for_each_role(): void
    {
        $resident = User::factory()->morador()->create();
        $reservation = Reservation::factory()->create(['user_id' => $resident->id, 'status' => ReservationStatus::Pending]);
        Reservation::factory()->create(['status' => ReservationStatus::Approved]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reservations.manage', ['status' => 'pending']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/reservation-management')
            ->has('reservations.data', 1)->where('reservations.data.0.id', $reservation->id)
            ->where('filters.status', 'pending'));
        $this->actingAs($resident)
            ->get(route('morador.reservations.manage'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('morador/reservation-management')
            ->has('reservations.data', 1)->where('reservations.data.0.id', $reservation->id));
        $this->get(route('admin.reservations.manage'))->assertForbidden();
    }
}
