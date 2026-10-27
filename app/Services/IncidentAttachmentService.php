<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IncidentAttachmentService
{
    public const DISK = 'incident_attachments';

    public const MAX_SIZE_KIB = 10 * 1024;

    public const FORMATS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/bmp' => ['bmp'],
        'image/x-ms-bmp' => ['bmp'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    public function store(User $actor, Incident $incident, UploadedFile $file): IncidentAttachment
    {
        $path = null;
        try {
            $attachment = DB::transaction(function () use ($actor, $incident, $file, &$path): IncidentAttachment {
                $current = Incident::query()->lockForUpdate()->findOrFail($incident->getKey());
                $actor = $this->resolveActor($actor, true);
                Gate::forUser($actor)->authorize('uploadAttachment', $current);
                Validator::make(['file' => $file, 'original_name' => $file->getClientOriginalName()], [
                    'original_name' => ['required', 'string', 'max:255'],
                    'file' => [
                        'bail', 'required', 'file', 'max:'.self::MAX_SIZE_KIB,
                        'mimetypes:'.implode(',', array_keys(self::FORMATS)),
                        'extensions:jpg,jpeg,png,gif,bmp,webp,pdf',
                        function (string $attribute, mixed $value, \Closure $fail): void {
                            $mime = $value->getMimeType();
                            if (! in_array(strtolower($value->getClientOriginalExtension()), self::FORMATS[$mime] ?? [], true)
                                || (str_starts_with($mime ?? '', 'image/') && @getimagesize($value->getPathname()) === false)) {
                                $fail('O conteúdo do arquivo não corresponde ao formato informado.');
                            }
                        },
                    ],
                ], [
                    'file.max' => 'Cada arquivo deve ter no máximo 10 MiB.',
                    'file.mimetypes' => 'Envie uma imagem JPG, PNG, GIF, BMP, WebP ou um PDF válido.',
                    'file.extensions' => 'A extensão do arquivo não é permitida.',
                    'file.uploaded' => 'Não foi possível enviar o arquivo. Tente novamente.',
                ])->validate();
                $mime = $file->getMimeType();
                $name = Str::uuid().'.'.self::FORMATS[$mime][0];
                $directory = 'incidents/'.$current->id;
                $path = $directory.'/'.$name;
                $stored = Storage::disk(self::DISK)->putFileAs($directory, $file, $name, ['visibility' => 'private']);
                if ($stored === false) {
                    throw new RuntimeException('Não foi possível armazenar o anexo privado.');
                }
                $attachment = new IncidentAttachment([
                    'original_name' => $file->getClientOriginalName(), 'path' => $path,
                    'mime_type' => $mime, 'size' => $file->getSize(),
                ]);
                $attachment->incident()->associate($current);
                $attachment->uploadedBy()->associate($actor);
                $attachment->uploaded_at = now();
                if (! $attachment->save()) {
                    throw new RuntimeException('Não foi possível persistir o anexo.');
                }

                return $attachment;
            });
            $this->registerRollbackCleanup($attachment->path);

            return $attachment;
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $exception;
        }
    }

    /** Released savepoints do not propagate rollback callbacks, so every enclosing transaction needs cleanup. */
    private function registerRollbackCleanup(string $path): void
    {
        $connection = DB::connection()->getName();
        app('db.transactions')->getPendingTransactions()
            ->filter(fn (DatabaseTransactionRecord $transaction): bool => $transaction->connection === $connection)
            ->each(function (DatabaseTransactionRecord $transaction) use ($path): void {
                $transaction->addCallbackForRollback(static function () use ($path): void {
                    Storage::disk(self::DISK)->delete($path);
                });
            });
    }

    public function download(User $actor, IncidentAttachment $attachment): StreamedResponse
    {
        $current = IncidentAttachment::query()->findOrFail($attachment->getKey());
        $actor = $this->resolveActor($actor);
        Gate::forUser($actor)->authorize('view', $current);
        abort_unless(Storage::disk(self::DISK)->exists($current->path), 404);

        return Storage::disk(self::DISK)->download(
            $current->path,
            str_replace(["\r", "\n"], '', $current->original_name),
            ['Content-Type' => $current->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
        );
    }

    private function resolveActor(User $actor, bool $lock = false): User
    {
        $query = User::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        $current = $actor->exists ? $query->find($actor->getKey()) : null;
        if ($current === null) {
            throw new AuthorizationException('Usuário inválido.');
        }

        return $current;
    }
}
