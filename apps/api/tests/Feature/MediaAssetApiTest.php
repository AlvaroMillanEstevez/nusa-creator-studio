<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAssetApiTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private const MP4_BASE64 = 'AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAANdbW9vdgAAAGxtdmhkAAAAAAAAAAAAAAAAAAAD6AAAAHgAAQAAAQAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgAAAod0cmFrAAAAXHRraGQAAAADAAAAAAAAAAAAAAABAAAAAAAAAHgAAAAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAABAAAAAABAAAAAQAAAAAAAkZWR0cwAAABxlbHN0AAAAAAAAAAEAAAB4AAAEAAABAAAAAAH/bWRpYQAAACBtZGhkAAAAAAAAAAAAAAAAAAAyAAAABgBVxAAAAAAALWhkbHIAAAAAAAAAAHZpZGUAAAAAAAAAAAAAAABWaWRlb0hhbmRsZXIAAAABqm1pbmYAAAAUdm1oZAAAAAEAAAAAAAAAAAAAACRkaW5mAAAAHGRyZWYAAAAAAAAAAQAAAAx1cmwgAAAAAQAAAWpzdGJsAAAAvnN0c2QAAAAAAAAAAQAAAK5hdmMxAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAABAAEABIAAAASAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAGP//AAAANGF2Y0MBZAAK/+EAF2dkAAqs2V7ARAAAAwAEAAADAMg8SJZYAQAGaOvjyyLA/fj4AAAAABBwYXNwAAAAAQAAAAEAAAAUYnRydAAAAAAAAL7iAAC+4gAAABhzdHRzAAAAAAAAAAEAAAADAAACAAAAABRzdHNzAAAAAAAAAAEAAAABAAAAKGN0dHMAAAAAAAAAAwAAAAEAAAQAAAAAAQAABgAAAAABAAACAAAAABxzdHNjAAAAAAAAAAEAAAABAAAAAwAAAAEAAAAgc3RzegAAAAAAAAAAAAAAAwAAAsUAAAAMAAAADAAAABRzdGNvAAAAAAAAAAEAAAONAAAAYnVkdGEAAABabWV0YQAAAAAAAAAhaGRscgAAAAAAAAAAbWRpcmFwcGwAAAAAAAAAAAAAAAAtaWxzdAAAACWpdG9vAAAAHWRhdGEAAAABAAAAAExhdmY1OC43Ni4xMDAAAAAIZnJlZQAAAuVtZGF0AAACrgYF//+q3EXpvebZSLeWLNgg2SPu73gyNjQgLSBjb3JlIDE2MyByMzA2MCA1ZGI2YWE2IC0gSC4yNjQvTVBFRy00IEFWQyBjb2RlYyAtIENvcHlsZWZ0IDIwMDMtMjAyMSAtIGh0dHA6Ly93d3cudmlkZW9sYW4ub3JnL3gyNjQuaHRtbCAtIG9wdGlvbnM6IGNhYmFjPTEgcmVmPTMgZGVibG9jaz0xOjA6MCBhbmFseXNlPTB4MzoweDExMyBtZT1oZXggc3VibWU9NyBwc3k9MSBwc3lfcmQ9MS4wMDowLjAwIG1peGVkX3JlZj0xIG1lX3JhbmdlPTE2IGNocm9tYV9tZT0xIHRyZWxsaXM9MSA4eDhkY3Q9MSBjcW09MCBkZWFkem9uZT0yMSwxMSBmYXN0X3Bza2lwPTEgY2hyb21hX3FwX29mZnNldD0tMiB0aHJlYWRzPTEgbG9va2FoZWFkX3RocmVhZHM9MSBzbGljZWRfdGhyZWFkcz0wIG5yPTAgZGVjaW1hdGU9MSBpbnRlcmxhY2VkPTAgYmx1cmF5X2NvbXBhdD0wIGNvbnN0cmFpbmVkX2ludHJhPTAgYmZyYW1lcz0zIGJfcHlyYW1pZD0yIGJfYWRhcHQ9MSBiX2JpYXM9MCBkaXJlY3Q9MSB3ZWlnaHRiPTEgb3Blbl9nb3A9MCB3ZWlnaHRwPTIga2V5aW50PTI1MCBrZXlpbnRfbWluPTI1IHNjZW5lY3V0PTQwIGludHJhX3JlZnJlc2g9MCByY19sb29rYWhlYWQ9NDAgcmM9Y3JmIG1idHJlZT0xIGNyZj0yMy4wIHFjb21wPTAuNjAgcXBtaW49MCBxcG1heD02OSBxcHN0ZXA9NCBpcF9yYXRpbz0xLjQwIGFxPTE6MS4wMACAAAAAD2WIhAAz//727L4FNhTIwQAAAAhBmiJsQr/+wAAAAAgBnkF5Cv/EgQ==';

    private const MP3_BASE64 = 'SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tAwAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAAFAAACwABoaGhoaGhoaGhoaGhoaGhoaGhojo6Ojo6Ojo6Ojo6Ojo6Ojo6Ojo60tLS0tLS0tLS0tLS0tLS0tLS0tNra2tra2tra2tra2tra2tra2tra//////////////////////////8AAAAATGF2YzU4LjEzAAAAAAAAAAAAAAAAJAMGAAAAAAAAAsBZicLAAAAAAAD/+xDEAAAEeCNhVDCAMJ4IqYM0cABFAG07pAAHd3dz8tw4GBgbmpQEEQfD/BDg+D76gQOFwx0+7l32Yd//6CYvb46I9Hc6y5E4RvZIdlIycIQG+C0eG342BU71A0JfnQaV///BkogABP/7EsQDg8VkLSYd54AgogWigb9lQMAYAEvyDADzAoBhMJYJswmEezKYFOMFcBUBAYgAAVSwvhJmK///tEDhlL4uCYCAGHiRkBearIGLZiGfxg5xhaAqnmsBlWbQC1qHab9Kaoj6sEhNLv/7EMQDg8UILRYAa+LApYWiQa/pQBGJCGeInXiGDADTJoNQHYYGAAQgYBuIgB1Ltpb1RGt+mn//1ElZ0CiAGXOAR4Iuk/4whYDINZ0ABxYIkE2YREAxNBOvCC7lvo//df//aMhusAoe//sSxAQDxPAtFg1/KgCzhaRCvPAE1RREywI6cMwXocKNA8A/zAvgCY+RwFIBh0h2GQ3Sfo//+aZuIwAQMAmpWGAAGA8DYYTAPRg9JNGr4KeYEQPhgmARGBEA4iwRgDQNB0r/chnERf3x//sQxAOABUQ7ThmWgACLhuHDmJAAkCBAKa1lYE7a4eOPRmhZlmuE7HOkY83JQERFgOFQV+Ij3vQ0ShKEoyeZJJNQwRA6WYDJ8QQInwFACBLlkTXBoGtnBXljykxBTUUzLjEwMKqqqqo=';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('media.disk', 'media-test');
        config()->set('media.max_size_kb', 51200);
        Storage::fake('media-test');
    }

    public function test_authenticated_user_can_upload_image_with_server_inferred_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedFile('launch <hero>.png', base64_decode(self::PNG_BASE64, true)),
                'type' => 'document',
            ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.original_name', 'launch _hero_.png')
            ->assertJsonPath('data.metadata.validation_source', 'server')
            ->assertJsonPath('data.metadata.uploaded_extension', 'png')
            ->assertJsonMissingPath('data.path');

        $asset = MediaAsset::query()->findOrFail($response->json('data.id'));

        $this->assertMatchesRegularExpression(
            '#^users/'.$user->id.'/media/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#',
            $asset->path,
        );
        $this->assertStringNotContainsString('launch', $asset->path);
        Storage::disk('media-test')->assertExists($asset->path);
    }

    public function test_authenticated_user_can_upload_video(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedFile('short-clip.mp4', base64_decode(self::MP4_BASE64, true)),
            ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.mime_type', 'video/mp4');
    }

    public function test_authenticated_user_can_upload_audio(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedFile('voice-note.mp3', base64_decode(self::MP3_BASE64, true)),
            ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.mime_type', 'audio/mpeg');
    }

    public function test_authenticated_user_can_upload_document_and_attach_it_to_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedFile('brief.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
                'project_id' => $project->id,
            ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.type', 'document')
            ->assertJsonPath('data.mime_type', 'application/pdf');
    }

    public function test_asset_cannot_be_attached_to_another_users_project(): void
    {
        $user = User::factory()->create();
        $otherProject = Project::factory()->for(User::factory()->create())->create();

        $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedFile('photo.png', base64_decode(self::PNG_BASE64, true)),
                'project_id' => $otherProject->id,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_user_can_list_only_their_assets_with_filters(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $own = MediaAsset::factory()->for($user)->create([
            'project_id' => $project->id,
            'type' => 'image',
        ]);
        MediaAsset::factory()->for($user)->create(['type' => 'document']);
        MediaAsset::factory()->for($other)->create(['type' => 'image']);

        $this->actingAs($user)
            ->getJson("/api/v1/media-assets?project_id={$project->id}&type=image")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_user_can_list_project_assets_but_not_another_users_project_assets(): void
    {
        $user = User::factory()->create();
        $ownProject = Project::factory()->for($user)->create();
        $otherProject = Project::factory()->for(User::factory()->create())->create();
        $asset = MediaAsset::factory()->for($user)->create(['project_id' => $ownProject->id]);
        MediaAsset::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/projects/{$ownProject->id}/media-assets")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $asset->id);

        $this->getJson("/api/v1/projects/{$otherProject->id}/media-assets")
            ->assertNotFound();
    }

    public function test_user_can_delete_own_asset_and_storage_file(): void
    {
        $user = User::factory()->create();
        $asset = MediaAsset::factory()->for($user)->create([
            'disk' => 'media-test',
            'path' => "users/{$user->id}/media/test-file.png",
        ]);
        Storage::disk('media-test')->put($asset->path, 'safe content');

        $this->actingAs($user)
            ->deleteJson("/api/v1/media-assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Media asset deleted successfully.');

        Storage::disk('media-test')->assertMissing($asset->path);
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    public function test_missing_storage_file_does_not_prevent_asset_deletion(): void
    {
        $user = User::factory()->create();
        $asset = MediaAsset::factory()->for($user)->create([
            'disk' => 'media-test',
            'path' => "users/{$user->id}/media/missing.png",
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/media-assets/{$asset->id}")
            ->assertOk();

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    public function test_user_cannot_delete_another_users_asset(): void
    {
        $user = User::factory()->create();
        $asset = MediaAsset::factory()->for(User::factory()->create())->create([
            'disk' => 'media-test',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/media-assets/{$asset->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_unauthenticated_users_cannot_access_media_endpoints(): void
    {
        $project = Project::factory()->create();
        $asset = MediaAsset::factory()->create();

        $this->getJson('/api/v1/media-assets')->assertUnauthorized();
        $this->postJson('/api/v1/media-assets')->assertUnauthorized();
        $this->getJson("/api/v1/projects/{$project->id}/media-assets")->assertUnauthorized();
        $this->deleteJson("/api/v1/media-assets/{$asset->id}")->assertUnauthorized();
    }

    public function test_disallowed_and_script_extensions_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['php', 'js', 'html', 'svg', 'sh', 'exe', 'zip'] as $extension) {
            $this->actingAs($user)
                ->post('/api/v1/media-assets', [
                    'file' => $this->uploadedFile("payload.{$extension}", '<?php echo "unsafe";'),
                ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        }

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_double_extensions_and_mime_extension_mismatches_are_rejected(): void
    {
        $user = User::factory()->create();
        $png = base64_decode(self::PNG_BASE64, true);

        $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedFile('image.png.php', $png),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->post('/api/v1/media-assets', [
            'file' => $this->uploadedFile('image.pdf', $png),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_svg_html_and_script_content_disguised_with_allowed_extensions_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach ([
            ['fake-image.png', '<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>'],
            ['notes.txt', '<!doctype html><html><script>alert(1)</script></html>'],
            ['code.txt', '<?php echo "unsafe";'],
        ] as [$name, $contents]) {
            $this->actingAs($user)
                ->post('/api/v1/media-assets', [
                    'file' => $this->uploadedFile($name, $contents),
                ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        }
    }

    public function test_files_over_the_configured_maximum_are_rejected(): void
    {
        $user = User::factory()->create();
        config()->set('media.max_size_kb', 1);

        $this->actingAs($user)
            ->post('/api/v1/media-assets', [
                'file' => $this->uploadedFile('large.txt', str_repeat('a', 2048)),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    private function uploadedFile(string $name, string|false $contents): UploadedFile
    {
        $this->assertIsString($contents);

        $path = tempnam(sys_get_temp_dir(), 'nusa-media-');
        $this->assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile(
            path: $path,
            originalName: $name,
            mimeType: null,
            error: UPLOAD_ERR_OK,
            test: true,
        );
    }
}
