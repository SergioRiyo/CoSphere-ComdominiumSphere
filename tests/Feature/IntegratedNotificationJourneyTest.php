<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Announcement;
use App\Models\CommonArea;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IntegratedNotificationJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_events_reach_one_shared_inbox_with_recipient_isolation_and_no_duplicate_deliveries(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 10:00:00'));
        $resident = User::factory()->morador()->create();
        $housemate = User::factory()->morador()->create(['unit_id' => $resident->unit_id]);
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $doorman = User::factory()->porteiro()->create();
        $area = CommonArea::factory()->create(['requires_approval' => true]);

        $this->actingAs($resident)->postJson(route('morador.reservations.store'), [
            'common_area_id' => $area->id, 'starts_at' => '2026-10-01 14:00:00', 'ends_at' => '2026-10-01 16:00:00',
        ])->assertCreated();
        $reservation = Reservation::sole();
        $this->actingAs($admin)->patchJson(route('admin.reservations.approve', $reservation))->assertOk();
        $this->patchJson(route('admin.reservations.approve', $reservation))->assertUnprocessable();

        $this->actingAs($resident)->post(route('morador.orders.store'), [
            'description' => 'Livros', 'tracking_code' => 'RF016-INTEGRACAO',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->actingAs($doorman)->patch(route('portaria.orders.receive', $order))->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('portaria.orders.receive', $order))->assertRedirect()->assertSessionHasErrors('order');

        $this->actingAs($resident)->post(route('morador.incidents.store'), [
            'title' => 'Portão avariado', 'description' => 'Detalhes privados da ocorrência', 'type' => 'incident', 'category' => 'maintenance',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $incident = Incident::sole();
        $this->actingAs($admin)->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress'])->assertOk();
        $this->patchJson(route('admin.incidents.status', $incident), ['status' => 'in_progress'])->assertUnprocessable();

        $this->post(route('admin.announcements.store'), ['title' => 'Assembleia', 'content' => 'Comunicado da assembleia.'])->assertRedirect();
        $announcement = Announcement::sole();
        $this->patch(route('admin.announcements.publish', $announcement))->assertRedirect();
        $this->patch(route('admin.announcements.publish', $announcement))->assertRedirect();

        $this->assertDatabaseCount('notifications', 9);
        $this->assertSame(6, $resident->notifications()->count());
        $this->assertSame(1, $housemate->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $otherAdmin->notifications()->count());
        $this->assertSame(0, $doorman->notifications()->count());

        $expectedEvents = [
            'Solicitação de reserva registrada' => [NotificationType::Reservation, 'Reserva'],
            'Reserva aprovada' => [NotificationType::Reservation, 'Reserva'],
            'Encomenda recebida na portaria' => [NotificationType::Package, 'Encomenda'],
            'Solicitação registrada' => [NotificationType::Occurrence, 'Ocorrência'],
            'Status da solicitação atualizado' => [NotificationType::Occurrence, 'Ocorrência'],
            'Assembleia' => [NotificationType::Announcement, 'Comunicado'],
        ];
        $residentEvents = $resident->notifications()->orderByDesc('sent_at')->orderByDesc('id')->get();
        $this->assertCount(count($expectedEvents), $residentEvents);
        $this->actingAs($resident)->get(route('notifications.index', ['recipient_id' => $admin->id]))
            ->assertInertia(function (Assert $page) use ($residentEvents, $expectedEvents, $announcement): void {
                $page->component('notifications/index')->where('notifications.total', 6)->has('notifications.data', 6);
                foreach ($residentEvents as $index => $notification) {
                    $this->assertArrayHasKey($notification->title, $expectedEvents);
                    [$type, $label] = $expectedEvents[$notification->title];
                    $this->assertSame($type, $notification->type);
                    $page->where("notifications.data.{$index}.id", $notification->id)
                        ->where("notifications.data.{$index}.title", $notification->title)
                        ->where("notifications.data.{$index}.type", $type->value)
                        ->where("notifications.data.{$index}.type_label", $label)
                        ->where("notifications.data.{$index}.announcement_id", $type === NotificationType::Announcement ? $announcement->id : null)
                        ->where("notifications.data.{$index}.is_read", false);
                }
            });
        foreach ($expectedEvents as $title => [$type]) {
            $this->assertSame(1, $resident->notifications()->where('title', $title)->where('type', $type->value)->count());
        }

        $adminNotification = $admin->notifications()->sole();
        $this->assertSame('Nova solicitação', $adminNotification->title);
        $this->assertStringContainsString($incident->title, $adminNotification->message);
        $this->assertStringNotContainsString($incident->description, $adminNotification->message);
        $this->actingAs($admin)->get(route('notifications.index', ['recipient_id' => $resident->id]))
            ->assertInertia(fn (Assert $page) => $page->component('notifications/index')->where('notifications.total', 1)
                ->where('notifications.data.0.id', $adminNotification->id)->where('notifications.data.0.title', 'Nova solicitação')
                ->where('notifications.data.0.type', 'occurrence')->where('notifications.data.0.type_label', 'Ocorrência'));
        $this->patch(route('notifications.read', $residentEvents->first()->id))->assertNotFound();
        $this->actingAs($resident)->patch(route('notifications.read', $adminNotification->id))->assertNotFound();
        $this->assertFalse($adminNotification->fresh()->is_read);
        $this->assertFalse($residentEvents->first()->fresh()->is_read);

        $this->actingAs($housemate)->get(route('notifications.index', ['recipient_id' => $resident->id]))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.total', 1)
                ->where('notifications.data.0.type', 'announcement')->where('notifications.data.0.announcement_id', $announcement->id));
        $this->actingAs($otherAdmin)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.total', 1)
                ->where('notifications.data.0.id', $otherAdmin->notifications()->sole()->id)
                ->where('notifications.data.0.type_label', 'Ocorrência'));
        $this->actingAs($doorman)->get(route('notifications.index'))->assertForbidden();

        $approved = $resident->notifications()->where('title', 'Reserva aprovada')->sole();
        $this->actingAs($resident)->from(route('notifications.index'))->patch(route('notifications.read', $approved))->assertRedirect(route('notifications.index'));
        $this->assertTrue($approved->fresh()->is_read);
        $this->assertSame(5, $resident->notifications()->where('is_read', false)->count());
        $this->get(route('morador.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->component('notifications/index')->where('notifications.total', 6));
    }

    public function test_existing_notification_types_and_labels_remain_readable_together(): void
    {
        $resident = User::factory()->morador()->create();
        $announcement = Announcement::factory()->published()->create();
        $labels = [
            'reservation' => 'Reserva', 'visitor' => 'Visitante', 'package' => 'Encomenda',
            'occurrence' => 'Ocorrência', 'system' => 'Sistema', 'announcement' => 'Comunicado',
        ];
        foreach ($labels as $type => $label) {
            Notification::factory()->create([
                'recipient_id' => $resident->id, 'type' => $type,
                'announcement_id' => $type === 'announcement' ? $announcement->id : null,
                'title' => $label, 'sent_at' => now(),
            ]);
        }
        $this->actingAs($resident)->get(route('notifications.index'))
            ->assertInertia(function (Assert $page) use ($labels): void {
                $page->component('notifications/index')->where('notifications.total', 6)->has('notifications.data', 6);
                foreach (array_reverse($labels, true) as $type => $label) {
                    $index = array_search($type, array_keys(array_reverse($labels, true)), true);
                    $page->where("notifications.data.{$index}.type", $type)->where("notifications.data.{$index}.type_label", $label);
                }
            });
    }
}
