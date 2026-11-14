<?php

namespace Tests\Feature;

use App\Models\LostFoundConversation;
use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LostFoundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_resident_can_publish_a_private_photo_and_other_residents_see_no_author_identity(): void
    {
        $author = User::factory()->morador()->create(['name' => 'Nome privado']);
        $other = User::factory()->morador()->create();
        $this->actingAs($author)->post(route('morador.lost-found.store'), [
            'type' => 'found', 'description' => 'Carteira azul', 'condition' => 'Boa',
            'location' => 'Portaria', 'event_date' => today()->toDateString(), 'photo' => UploadedFile::fake()->image('item.png'),
        ])->assertRedirect();
        $item = LostFoundItem::query()->sole();
        Storage::disk('local')->assertExists($item->photo_path);
        $this->assertSame('created', $item->history()->first()->action);
        $this->actingAs($other)->get(route('morador.lost-found.show', $item))
            ->assertInertia(fn (Assert $page) => $page->where('item.description', 'Carteira azul')->missing('item.author')->missing('item.history')->missing('item.photo_path'));
        $this->get(route('morador.lost-found.photo', $item))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->porteiro()->create())->get(route('morador.lost-found.photo', $item))->assertForbidden();
    }

    public function test_upload_validation_rejects_invalid_image_and_future_date(): void
    {
        $this->actingAs(User::factory()->morador()->create())->post(route('morador.lost-found.store'), [
            'type' => 'found', 'description' => 'Item', 'condition' => 'Boa', 'location' => 'Portaria',
            'event_date' => today()->addDay()->toDateString(), 'photo' => UploadedFile::fake()->createWithContent('foto.png', '<?php echo 1;'),
        ])->assertSessionHasErrors(['photo', 'event_date']);
        $this->assertDatabaseCount('lost_found_items', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->post(route('morador.lost-found.store'), [
            'type' => 'found', 'description' => 'Item', 'condition' => 'Boa', 'location' => 'Portaria',
            'event_date' => today()->toDateString(), 'photo' => UploadedFile::fake()->image('grande.png')->size(5121),
        ])->assertSessionHasErrors('photo');
    }

    public function test_conversations_are_private_to_sender_and_administration(): void
    {
        $item = LostFoundItem::factory()->create();
        $first = User::factory()->morador()->create(['name' => 'Primeiro']);
        $second = User::factory()->morador()->create(['name' => 'Segundo']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($first)->post(route('morador.lost-found.message', $item), ['body' => 'Mensagem secreta 1'])->assertRedirect();
        $thread = LostFoundConversation::query()->sole();
        $this->actingAs($second)->post(route('morador.lost-found.message', $item), ['body' => 'Mensagem secreta 2'])->assertRedirect();
        $response = $this->get(route('morador.lost-found.show', $item));
        $response->assertInertia(fn (Assert $page) => $page->has('item.conversations', 1)->where('item.conversations.0.messages.0.body', 'Mensagem secreta 2'));
        $this->assertStringNotContainsString('Mensagem secreta 1', $response->getContent());
        $this->post(route('morador.lost-found.reply', [$item, $thread]), ['body' => 'invasão'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.lost-found.reply', [$item, $thread]), ['body' => 'Resposta administrativa'])->assertRedirect();
        $this->get(route('admin.lost-found.show', $item))->assertInertia(fn (Assert $page) => $page->has('item.conversations', 2)->where('item.author.name', $item->author->name));
        $this->actingAs($first)->get(route('morador.lost-found.show', $item))->assertInertia(fn (Assert $page) => $page
            ->has('item.conversations.0.messages', 2)->where('item.conversations.0.messages.1.sender', 'Administração'));
    }

    public function test_edit_status_finalize_reopen_and_default_list(): void
    {
        $author = User::factory()->morador()->create();
        $other = User::factory()->morador()->create();
        $admin = User::factory()->admin()->create();
        $item = LostFoundItem::factory()->create(['author_id' => $author->id]);
        $payload = ['type' => 'lost', 'description' => 'Chave', 'condition' => 'Boa', 'location' => 'Piscina', 'event_date' => today()->toDateString()];
        $this->actingAs($other)->put(route('morador.lost-found.update', $item), $payload)->assertForbidden();
        $this->actingAs($author)->put(route('morador.lost-found.update', $item), $payload)->assertRedirect();
        $this->patch(route('morador.lost-found.status', $item), ['status' => 'in_progress'])->assertForbidden();
        $this->patch(route('morador.lost-found.status', $item), ['status' => 'resolved'])->assertRedirect();
        $this->put(route('morador.lost-found.update', $item), $payload)->assertForbidden();
        $this->get(route('morador.lost-found.index'))->assertInertia(fn (Assert $page) => $page->has('items.data', 0));
        $this->get(route('morador.lost-found.index', ['status' => 'resolved']))->assertInertia(fn (Assert $page) => $page->has('items.data', 1));
        $this->actingAs($admin)->patch(route('admin.lost-found.status', $item), ['status' => 'open'])->assertRedirect();
        $this->assertSame('open', $item->fresh()->status);
        $this->assertSame(['status_changed', 'status_changed', 'updated'], $item->history()->pluck('action')->all());
    }

    public function test_author_can_replace_and_remove_photo_and_photo_endpoints_are_protected(): void
    {
        $author = User::factory()->morador()->create();
        $this->actingAs($author)->post(route('morador.lost-found.store'), [
            'type' => 'lost', 'description' => 'Item', 'condition' => 'Boa', 'location' => 'Portaria',
            'event_date' => today()->toDateString(), 'photo' => UploadedFile::fake()->image('original.png'),
        ])->assertRedirect();
        $item = LostFoundItem::query()->sole();
        $oldPath = $item->photo_path;
        $payload = ['type' => 'lost', 'description' => 'Item editado', 'condition' => 'Boa',
            'location' => 'Portaria', 'event_date' => today()->toDateString()];
        $this->put(route('morador.lost-found.update', $item), $payload + ['photo' => UploadedFile::fake()->image('nova.jpg')])->assertRedirect();
        $newPath = $item->fresh()->photo_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertExists($newPath);
        Storage::disk('local')->assertMissing($oldPath);
        $this->put(route('morador.lost-found.update', $item), $payload + ['remove_photo' => true])->assertRedirect();
        $this->assertNull($item->fresh()->photo_path);
        Storage::disk('local')->assertMissing($newPath);
        $this->get(route('morador.lost-found.photo', $item))->assertNotFound();
        $this->actingAs(User::factory()->morador()->unverified()->create())->get(route('morador.lost-found.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->morador()->inactive()->create())->get(route('morador.lost-found.index'))->assertRedirect(route('login'));
    }

    public function test_resolved_items_reject_new_messages_and_cross_item_replies(): void
    {
        $resident = User::factory()->morador()->create();
        $item = LostFoundItem::factory()->create(['status' => 'resolved']);
        $this->actingAs($resident)->post(route('morador.lost-found.message', $item), ['body' => 'Mensagem'])->assertSessionHasErrors('body');
        $thread = LostFoundConversation::factory()->create(['resident_id' => $resident->id]);
        $other = LostFoundItem::factory()->create();
        $this->post(route('morador.lost-found.reply', [$other, $thread]), ['body' => 'Mensagem'])->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.lost-found.reply', [$item, $thread]), ['body' => 'Mensagem'])->assertNotFound();
        $this->assertDatabaseCount('lost_found_messages', 0);
    }
}
