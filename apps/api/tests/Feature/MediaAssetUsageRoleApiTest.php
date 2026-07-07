<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAssetUsageRoleApiTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('media.disk', 'media-test');
        Storage::fake('media-test');
    }

    public function test_upload_accepts_and_returns_usage_role(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['type' => 'fashion_try_on']);

        $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedPng('model.png'),
                'project_id' => $project->id,
                'usage_role' => 'model_reference',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.usage_role', 'model_reference');

        $this->getJson("/api/v1/projects/{$project->id}/media-assets")
            ->assertOk()
            ->assertJsonPath('data.0.usage_role', 'model_reference');
    }

    public function test_upload_rejects_unknown_usage_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedPng('model.png'),
                'usage_role' => 'credit_card_photo',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('usage_role');
    }

    private function uploadedPng(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'nusa-media-');
        $this->assertIsString($path);
        file_put_contents($path, base64_decode(self::PNG_BASE64, true));

        return new UploadedFile(
            path: $path,
            originalName: $name,
            mimeType: null,
            error: UPLOAD_ERR_OK,
            test: true,
        );
    }
}