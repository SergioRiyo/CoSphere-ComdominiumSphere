<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class SupabaseStorageTest extends TestCase
{
    public function test_supabase_upload_does_not_send_unsupported_acl_header(): void
    {
        $requests = [];
        config([
            'filesystems.supabase_s3' => true,
            'filesystems.disks.s3' => [
                'driver' => 's3', 'key' => 'example-key', 'secret' => 'example-secret',
                'region' => 'us-east-1', 'bucket' => 'private', 'endpoint' => 'https://example.test',
                'use_path_style_endpoint' => true, 'throw' => true,
                'http_handler' => function (RequestInterface $request) use (&$requests) {
                    $requests[] = $request;

                    return Create::promiseFor(new Response(200));
                },
            ],
        ]);

        $this->app->getProvider(AppServiceProvider::class)->boot();
        Storage::disk('s3')->put('sample.txt', 'sample', ['visibility' => 'private']);

        $this->assertCount(1, $requests);
        $this->assertFalse($requests[0]->hasHeader('x-amz-acl'));
    }
}
