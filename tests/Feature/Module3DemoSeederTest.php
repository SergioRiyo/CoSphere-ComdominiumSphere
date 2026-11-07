<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Models\Announcement;
use App\Models\CommonArea;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Poll;
use App\Models\User;
use App\Services\PollService;
use Database\Seeders\Module3DemoSeeder;
use Database\Seeders\Module5DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class Module3DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_five_demo_is_repeatable_and_preserves_deliveries_and_votes(): void
    {
        config(['module3-demo.password' => 'temporary-demo-test-password']);
        $this->seed(Module5DemoSeeder::class);
        $resident = User::where('email', 'morador@m3-demo.cosphere.test')->firstOrFail();
        $announcement = Announcement::whereNotNull('published_at')->sole();
        $poll = Poll::whereNotNull('published_at')->sole();
        app(PollService::class)->vote($resident, $poll, $poll->options()->firstOrFail()->id);

        $this->seed(Module5DemoSeeder::class);

        $this->assertDatabaseCount('announcements', 2);
        $this->assertDatabaseCount('polls', 2);
        $this->assertDatabaseCount('poll_votes', 1);
        $this->assertSame(3, $announcement->notifications()->count());
        $this->assertSame(1, $announcement->notifications()->where('recipient_id', $resident->id)->count());
        $this->assertSame(3, $poll->options()->count());
        $this->actingAs($resident)->get(route('morador.announcements.index'))->assertOk();
        $this->actingAs($resident)->get(route('morador.polls.show', $poll))->assertOk();
    }

    public function test_demo_data_is_repeatable_and_uses_real_order_notification_contract(): void
    {
        config(['module3-demo.password' => 'temporary-demo-test-password']);
        $this->seed(Module3DemoSeeder::class);
        $this->seed(Module3DemoSeeder::class);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('units', 2);
        $this->assertDatabaseCount('common_areas', 2);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('notifications', 1);
        $resident = User::where('email', 'morador@m3-demo.cosphere.test')->firstOrFail();
        $this->assertTrue($resident->hasVerifiedEmail());
        $this->assertTrue(Hash::check('temporary-demo-test-password', $resident->password));
        $this->assertSame(1, CommonArea::where('requires_approval', false)->count());
        $this->assertSame(1, CommonArea::where('requires_approval', true)->count());
        $this->assertSame(OrderStatus::WaitingDelivery, Order::where('tracking_code', 'DEMO-PREVISTA')->firstOrFail()->status);
        $this->assertSame(OrderStatus::ReceivedAtGate, Order::where('tracking_code', 'DEMO-RECEBIDA')->firstOrFail()->status);
        $this->assertSame(NotificationType::Package, Notification::sole()->type);
        $this->assertSame($resident->id, Notification::sole()->recipient_id);
    }

    public function test_demo_seeder_refuses_production_before_writing(): void
    {
        $this->app['env'] = 'production';
        try {
            (new Module3DemoSeeder)->run();
            $this->fail('Production must be refused.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('banco local exclusivo', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_seeder_refuses_a_shared_database_even_in_testing(): void
    {
        $connection = DB::connection();
        $connection->setDatabaseName('shared-development-database');
        try {
            (new Module3DemoSeeder)->run();
            $this->fail('A shared database must be refused.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('banco local exclusivo', $exception->getMessage());
        } finally {
            $connection->setDatabaseName(':memory:');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_seeder_requires_an_external_temporary_password(): void
    {
        config(['module3-demo.password' => null]);
        try {
            $this->seed(Module3DemoSeeder::class);
            $this->fail('A temporary password must be provided.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('MODULE3_DEMO_PASSWORD', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
    }
}
