<?php

namespace Tests\Feature\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPng(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-photo-');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_replacing_profile_photo_returns_a_new_cache_busted_url(): void
    {
        Storage::fake('public');

        $user = $this->actingAsDomiciliataire();

        $first = $this->post('/api/profile/photo', [
            'photo' => $this->tinyPng('first.png'),
        ])->assertOk();

        $firstUrl = $first->json('data.photo_url');

        $second = $this->post('/api/profile/photo', [
            'photo' => $this->tinyPng('second.png'),
        ])->assertOk();

        $secondUrl = $second->json('data.photo_url');

        $this->assertNotNull($firstUrl);
        $this->assertNotNull($secondUrl);
        $this->assertNotSame($firstUrl, $secondUrl);
        $this->assertStringContainsString('/api/users/' . $user->id . '/photo', $secondUrl);
        $this->assertStringContainsString('?v=', $secondUrl);
    }
}
