<?php

namespace Tests\Feature\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacing_profile_photo_returns_a_new_cache_busted_url(): void
    {
        Storage::fake('public');

        $user = $this->actingAsDomiciliataire();

        $first = $this->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('first.jpg', 80, 80),
        ])->assertOk();

        $firstUrl = $first->json('data.photo_url');

        $second = $this->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('second.jpg', 80, 80),
        ])->assertOk();

        $secondUrl = $second->json('data.photo_url');

        $this->assertNotNull($firstUrl);
        $this->assertNotNull($secondUrl);
        $this->assertNotSame($firstUrl, $secondUrl);
        $this->assertStringContainsString('/api/users/' . $user->id . '/photo', $secondUrl);
        $this->assertStringContainsString('?v=', $secondUrl);
    }
}
