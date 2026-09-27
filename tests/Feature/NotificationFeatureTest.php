<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_only_contains_recipient_notifications_even_with_a_forged_recipient(): void
    {
        $resident = User::factory()->create();
        $coResident = User::factory()->create(['unit_id' => $resident->unit_id]);
        Notification::factory()->create(['recipient_id' => $coResident->id]);
        Notification::factory()->create();
        $notification = Notification::factory()->create([
            'recipient_id' => $resident->id,
            'type' => NotificationType::Reservation,
            'is_read' => false,
        ]);

        $this->actingAs($resident)->get(route('morador.notifications.index', ['recipient_id' => $coResident->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('morador/notifications/index')
                ->has('notifications.data', 1)
                ->where('notifications.total', 1)
                ->where('notifications.data.0.id', $notification->id)
                ->where('notifications.data.0.title', $notification->title)
                ->where('notifications.data.0.message', $notification->message)
                ->where('notifications.data.0.type', 'reservation')
                ->where('notifications.data.0.sent_at', $notification->sent_at->toISOString())
                ->where('notifications.data.0.is_read', false));

        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_list_is_paginated_and_sorted_by_sent_date_then_id_with_null_dates_last(): void
    {
        $resident = User::factory()->create();
        $undated = Notification::factory()->create(['recipient_id' => $resident->id, 'sent_at' => null]);
        Notification::factory()->count(9)->create(['recipient_id' => $resident->id, 'sent_at' => now()->subDay()]);
        $first = Notification::factory()->create(['recipient_id' => $resident->id, 'sent_at' => now()]);
        $second = Notification::factory()->create(['recipient_id' => $resident->id, 'sent_at' => $first->sent_at]);

        $this->actingAs($resident)->get(route('morador.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 10)
                ->where('notifications.total', 12)
                ->where('notifications.data.0.id', $second->id)
                ->where('notifications.data.1.id', $first->id));

        $this->get(route('morador.notifications.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 2)
                ->where('notifications.data.1.id', $undated->id)
                ->where('notifications.data.1.sent_at', null));
    }

    public function test_resident_without_notifications_receives_an_empty_list(): void
    {
        $this->actingAs(User::factory()->create(['unit_id' => null]))
            ->get(route('morador.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0)->where('notifications.total', 0));
    }

    public function test_marking_as_read_only_updates_read_state_and_is_idempotent(): void
    {
        $resident = User::factory()->create();
        $notification = Notification::factory()->create(['recipient_id' => $resident->id, 'is_read' => false]);
        $other = Notification::factory()->create(['is_read' => false]);

        $this->actingAs($resident)->from(route('morador.notifications.index'))
            ->patch(route('morador.notifications.read', $notification->id), [
                'recipient_id' => $other->recipient_id,
                'title' => 'Título adulterado',
                'is_read' => false,
            ])->assertRedirect(route('morador.notifications.index'));

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'recipient_id' => $resident->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'sent_at' => $notification->sent_at,
            'is_read' => true,
        ]);
        $updatedAt = $notification->fresh()->updated_at;
        $this->travel(1)->minutes();
        $this->patch(route('morador.notifications.read', $notification->id))->assertRedirect();
        $this->assertTrue($notification->fresh()->updated_at->equalTo($updatedAt));
        $this->assertFalse($other->fresh()->is_read);
    }

    public function test_other_users_notifications_cannot_be_marked_as_read_including_same_unit(): void
    {
        $resident = User::factory()->create();
        $coResident = User::factory()->create(['unit_id' => $resident->unit_id]);

        foreach ([$coResident, User::factory()->create()] as $other) {
            $notification = Notification::factory()->create(['recipient_id' => $other->id, 'is_read' => false]);
            $this->actingAs($resident)->patch(route('morador.notifications.read', $notification->id), [
                'recipient_id' => $resident->id,
            ])->assertNotFound();
            $this->assertFalse($notification->fresh()->is_read);
        }
    }

    public function test_deleted_and_missing_notifications_are_not_accessible(): void
    {
        $resident = User::factory()->create();
        $notification = Notification::factory()->create(['recipient_id' => $resident->id, 'is_read' => false]);
        $notification->delete();

        $this->actingAs($resident)->get(route('morador.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0));
        $this->patch(route('morador.notifications.read', $notification->id))->assertNotFound();
        $this->patch(route('morador.notifications.read', $notification->id + 1))->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'is_read' => false]);
    }

    public function test_routes_require_an_active_verified_resident(): void
    {
        $notification = Notification::factory()->create(['is_read' => false]);
        $index = route('morador.notifications.index');
        $read = route('morador.notifications.read', $notification->id);

        $this->get($index)->assertRedirect(route('login'));
        $this->patch($read)->assertRedirect(route('login'));

        foreach ([User::factory()->admin()->create(), User::factory()->porteiro()->create()] as $user) {
            $this->actingAs($user)->get($index)->assertForbidden();
            $this->patch($read)->assertForbidden();
        }

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get($index)->assertRedirect(route('login'));
        $this->actingAs($inactive)->patch($read)->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get($index)->assertRedirect(route('verification.notice'));
        $this->patch($read)->assertRedirect(route('verification.notice'));
        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_order_notification_and_read_state_persist_after_logout_and_login(): void
    {
        $resident = User::factory()->create();
        $doorman = User::factory()->porteiro()->create();
        app(OrderService::class)->createUnexpectedByDoorman([
            'resident_id' => $resident->id,
            'unit_id' => $resident->unit_id,
            'description' => 'Livros',
        ], $doorman);
        $notification = $resident->notifications()->sole();

        $this->actingAs($resident)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->post(route('login'), ['email' => $resident->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('morador.notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.id', $notification->id)
            ->where('notifications.data.0.type', 'package')
            ->where('notifications.data.0.title', 'Encomenda recebida na portaria')
            ->where('notifications.data.0.is_read', false));

        $this->from(route('morador.notifications.index'))->patch(route('morador.notifications.read', $notification->id))
            ->assertRedirect(route('morador.notifications.index'));
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->post(route('login'), ['email' => $resident->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('morador.notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.id', $notification->id)
            ->where('notifications.data.0.is_read', true));

    }
}
