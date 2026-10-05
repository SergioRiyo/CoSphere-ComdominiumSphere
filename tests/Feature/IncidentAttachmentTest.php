<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\IncidentAttachmentService;
use App\Services\IncidentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class IncidentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<resource> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(IncidentAttachmentService::DISK, config('filesystems.disks.'.IncidentAttachmentService::DISK));
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            fclose($file);
        }
        parent::tearDown();
    }

    #[DataProvider('allowedFormats')]
    public function test_real_raster_images_and_pdf_are_stored_privately_with_generated_names(string $extension, string $expectedMime): void
    {
        $incident = Incident::factory()->create();
        $content = $extension === 'pdf' ? $this->pdf() : $this->image($extension);
        $file = $this->upload('evidência.'.$extension, $content);
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, $file)->refresh();
        $this->assertSame('evidência.'.$extension, $attachment->original_name);
        $this->assertContains($attachment->mime_type, $extension === 'bmp' ? ['image/bmp', 'image/x-ms-bmp'] : [$expectedMime]);
        $this->assertSame(strlen($content), $attachment->size);
        $this->assertSame($incident->resident_id, $attachment->uploadedBy->id);
        $this->assertSame($incident->id, $attachment->incident->id);
        $this->assertSame($attachment->id, $incident->attachments()->sole()->id);
        $this->assertSame(now()->toDateTimeString(), $attachment->uploaded_at->toDateTimeString());
        $this->assertMatchesRegularExpression('#^incidents/'.$incident->id.'/[0-9a-f-]{36}\\.[a-z]+$#', $attachment->path);
        $this->assertStringNotContainsString('evidência', $attachment->path);
        $this->assertArrayNotHasKey('path', $attachment->toArray());
        Storage::disk(IncidentAttachmentService::DISK)->assertExists($attachment->path);
        $this->assertSame('private', Storage::disk(IncidentAttachmentService::DISK)->getConfig()['visibility']);
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame('private', Storage::disk(IncidentAttachmentService::DISK)->getVisibility($attachment->path));
        }
        $this->assertSame($content, Storage::disk(IncidentAttachmentService::DISK)->get($attachment->path));
    }

    public static function allowedFormats(): array
    {
        return [
            ['jpg', 'image/jpeg'], ['jpeg', 'image/jpeg'], ['png', 'image/png'],
            ['gif', 'image/gif'], ['bmp', 'image/x-ms-bmp'], ['webp', 'image/webp'],
            ['pdf', 'application/pdf'],
        ];
    }

    #[DataProvider('invalidFiles')]
    public function test_server_rejects_invalid_content_extensions_mime_spoofing_and_oversize_files(string $scenario): void
    {
        $incident = Incident::factory()->create();
        [$name, $content, $clientMime] = match ($scenario) {
            'script' => ['evidence.png', '<?php echo "malicious";', 'image/png'],
            'svg' => ['evidence.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/png'],
            'extension' => ['evidence.exe', $this->image('png'), 'image/png'],
            'mismatch' => ['evidence.png', $this->pdf(), 'image/png'],
            'broken_image' => ['evidence.png', "\x89PNG\r\n\x1a\n".str_repeat('invalid', 10), 'image/png'],
            'text_pdf' => ['evidence.pdf', 'This is not a PDF.', 'application/pdf'],
            'oversize' => ['evidence.pdf', str_pad($this->pdf(), 10 * 1024 * 1024 + 1, ' '), 'application/pdf'],
        };
        try {
            app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload($name, $content, $clientMime));
            $this->fail('Expected invalid upload.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('incident_attachments', 0);
            $this->assertSame([], Storage::disk(IncidentAttachmentService::DISK)->allFiles());
        }
    }

    public static function invalidFiles(): array
    {
        return [['script'], ['svg'], ['extension'], ['mismatch'], ['broken_image'], ['text_pdf'], ['oversize']];
    }

    public function test_exact_ten_mib_limit_is_allowed_and_size_is_recorded_in_bytes(): void
    {
        $incident = Incident::factory()->create();
        $content = str_pad($this->pdf(), 10 * 1024 * 1024, ' ');
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $content));
        $this->assertSame(10 * 1024 * 1024, $attachment->size);
    }

    public function test_owner_and_admin_can_download_through_authenticated_policy_route(): void
    {
        $incident = Incident::factory()->create();
        $content = $this->pdf();
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $content));
        foreach ([$incident->resident, User::factory()->admin()->create()] as $actor) {
            $response = $this->actingAs($actor)->get(route('incident-attachments.download', $attachment));
            $response->assertOk()->assertDownload('evidence.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($content, $response->streamedContent());
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_other_residents_same_unit_staff_inactive_and_unverified_users_are_denied(): void
    {
        $incident = Incident::factory()->create();
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $this->pdf()));
        $actors = [
            User::factory()->morador()->create(['unit_id' => $incident->unit_id]),
            User::factory()->morador()->create(),
            User::factory()->porteiro()->create(),
            User::factory()->admin()->inactive()->create(),
            User::factory()->morador()->unverified()->create(['unit_id' => $incident->unit_id]),
        ];
        foreach ($actors as $actor) {
            try {
                app(IncidentAttachmentService::class)->download($actor, $attachment);
                $this->fail('Unauthorized attachment download.');
            } catch (AuthorizationException) {
                $this->assertModelExists($attachment);
            }
            try {
                app(IncidentAttachmentService::class)->store($actor, $incident, $this->upload('other.pdf', $this->pdf()));
                $this->fail('Unauthorized attachment upload.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('incident_attachments', 1);
            }
        }
        foreach (array_slice($actors, 0, 3) as $actor) {
            $this->actingAs($actor)->getJson(route('incident-attachments.download', $attachment))->assertForbidden();
        }
    }

    public function test_guest_download_requires_authentication(): void
    {
        $attachment = IncidentAttachment::factory()->create();
        $this->getJson(route('incident-attachments.download', $attachment))->assertUnauthorized();
    }

    public function test_uploader_comes_from_reloaded_actor_and_admin_may_upload(): void
    {
        $incident = Incident::factory()->create();
        $admin = User::factory()->admin()->create();
        $attachment = app(IncidentAttachmentService::class)->store($admin, $incident, $this->upload('evidence.pdf', $this->pdf()));
        $this->assertSame($admin->id, $attachment->uploaded_by_user_id);
        User::whereKey($admin->id)->update(['role' => UserRole::Porteiro]);
        $this->expectException(AuthorizationException::class);
        app(IncidentAttachmentService::class)->store($admin, $incident, $this->upload('second.pdf', $this->pdf()));
    }

    #[DataProvider('persistenceFailures')]
    public function test_persistence_failures_leave_no_metadata_or_orphan_files(bool $throw): void
    {
        $incident = Incident::factory()->create();
        $baseline = DB::transactionLevel();
        IncidentAttachment::creating(function () use ($throw): bool {
            if ($throw) {
                throw new RuntimeException('metadata failed');
            }

            return false;
        });
        try {
            app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $this->pdf()));
            $this->fail('Expected metadata failure.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('incident_attachments', 0);
            $this->assertSame([], Storage::disk(IncidentAttachmentService::DISK)->allFiles());
            $this->assertSame($baseline, DB::transactionLevel());
        } finally {
            IncidentAttachment::flushEventListeners();
        }
    }

    public static function persistenceFailures(): array
    {
        return [[true], [false]];
    }

    public function test_storage_write_failure_cleans_partial_file_and_does_not_create_metadata(): void
    {
        $incident = Incident::factory()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $path = null;
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function (string $directory, UploadedFile $file, string $name, array $options) use (&$path): bool {
            $path = $directory.'/'.$name;
            $this->assertSame(['visibility' => 'private'], $options);

            return false;
        });
        $disk->shouldReceive('delete')->once()->withArgs(function (string $candidate) use (&$path): bool {
            return $path !== null && $candidate === $path;
        })->andReturn(true);
        Storage::shouldReceive('disk')->with(IncidentAttachmentService::DISK)->andReturn($disk);
        try {
            app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $this->pdf()));
            $this->fail('Expected storage failure.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('incident_attachments', 0);
        }
    }

    public function test_outer_transaction_rollback_also_removes_the_uploaded_file(): void
    {
        $incident = Incident::factory()->create();
        DB::beginTransaction();
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $this->pdf()));
        Storage::disk(IncidentAttachmentService::DISK)->assertExists($attachment->path);
        DB::rollBack();
        $this->assertDatabaseCount('incident_attachments', 0);
        Storage::disk(IncidentAttachmentService::DISK)->assertMissing($attachment->path);
    }

    public function test_outer_transaction_commit_retains_metadata_and_file(): void
    {
        $incident = Incident::factory()->create();
        DB::beginTransaction();
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $this->pdf()));
        DB::commit();
        $this->assertModelExists($attachment);
        Storage::disk(IncidentAttachmentService::DISK)->assertExists($attachment->path);
    }

    public function test_committed_inner_savepoint_does_not_leave_a_file_after_outer_rollback(): void
    {
        $incident = Incident::factory()->create();
        DB::beginTransaction();
        DB::beginTransaction();
        $attachment = app(IncidentAttachmentService::class)->store($incident->resident, $incident, $this->upload('evidence.pdf', $this->pdf()));
        DB::commit();
        Storage::disk(IncidentAttachmentService::DISK)->assertExists($attachment->path);
        DB::rollBack();
        $this->assertDatabaseCount('incident_attachments', 0);
        Storage::disk(IncidentAttachmentService::DISK)->assertMissing($attachment->path);
    }

    public function test_private_disk_has_no_public_serving_or_temporary_urls(): void
    {
        $config = config('filesystems.disks.'.IncidentAttachmentService::DISK);
        $this->assertSame('private', $config['visibility']);
        $this->assertFalse($config['serve']);
        $this->assertTrue($config['throw']);
        $this->assertStringContainsString('private', $config['root']);
        $this->assertArrayNotHasKey('url', $config);
        $realDisk = Storage::build(array_replace($config, ['root' => Storage::disk(IncidentAttachmentService::DISK)->path('')]));
        $this->assertFalse($realDisk->providesTemporaryUrls());
        $this->assertFalse(Route::has('storage.'.IncidentAttachmentService::DISK));
        $this->expectException(RuntimeException::class);
        $realDisk->temporaryUrl('incidents/1/evidence.pdf', now()->addMinute());
    }

    public function test_soft_delete_retains_files_metadata_history_and_maintenance_with_authorized_access(): void
    {
        $resident = User::factory()->morador()->create();
        $incident = app(IncidentService::class)->create($resident, ['title' => 'Vazamento', 'description' => 'Consertar vazamento', 'category' => 'maintenance']);
        $request = MaintenanceRequest::factory()->linkedToIncident($incident)->create();
        $attachment = app(IncidentAttachmentService::class)->store($resident, $incident, $this->upload('evidence.pdf', $this->pdf()));
        $history = $incident->statusHistory()->sole();
        $incident->delete();
        $this->assertSoftDeleted($incident);
        $this->assertModelExists($request);
        $this->assertModelExists($attachment);
        $this->assertModelExists($history);
        Storage::disk(IncidentAttachmentService::DISK)->assertExists($attachment->path);
        $this->assertSame($incident->id, $history->fresh()->incident->id);
        $this->assertSame($incident->id, $request->fresh()->incident->id);
        $this->actingAs($resident)->get(route('incident-attachments.download', $attachment))->assertOk();
        $this->assertFalse(Gate::forUser($resident)->allows('uploadAttachment', $incident));
    }

    public function test_uploader_and_incident_foreign_keys_protect_retained_attachments(): void
    {
        $attachment = IncidentAttachment::factory()->create();
        foreach ([$attachment->uploadedBy, $attachment->incident] as $model) {
            try {
                DB::transaction(fn () => $model instanceof Incident ? $model->forceDelete() : $model->delete());
                $this->fail('Attachment reference must be retained.');
            } catch (QueryException) {
                $this->assertModelExists($model);
            }
        }
        $this->assertModelExists($attachment);
    }

    public function test_missing_file_returns_not_found_only_after_authorization(): void
    {
        $attachment = IncidentAttachment::factory()->create();
        $this->actingAs($attachment->incident->resident)->getJson(route('incident-attachments.download', $attachment))->assertNotFound();
        $this->actingAs(User::factory()->morador()->create())->getJson(route('incident-attachments.download', $attachment))->assertForbidden();
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    }

    private function image(string $extension): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image),
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'bmp' => imagebmp($image),
            'webp' => imagewebp($image),
        };

        return ob_get_clean();
    }

    private function upload(string $name, string $content, ?string $clientMime = null): UploadedFile
    {
        $file = tmpfile();
        fwrite($file, $content);
        $this->files[] = $file;

        return new UploadedFile(stream_get_meta_data($file)['uri'], $name, $clientMime, null, true);
    }
}
