<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_authenticated_layouts_expose_the_same_pwa_metadata(): void
    {
        $pages = [$this->get('/login')->assertOk()];
        $pages[] = $this->actingAs(User::factory()->create())->get('/')->assertOk();

        foreach ($pages as $page) {
            $page->assertSee('<link rel="manifest" href="'.asset('manifest.webmanifest').'">', false)
                ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="'.asset('apple-touch-icon.png').'">', false)
                ->assertSee('<meta name="theme-color" content="#ffffff">', false)
                ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
                ->assertSee('<meta name="apple-mobile-web-app-status-bar-style" content="default">', false)
                ->assertSee('<meta name="apple-mobile-web-app-title" content="MyMilestones">', false);
        }
    }

    public function test_manifest_launches_the_application_in_standalone_mode(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('MyMilestones', $manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#ffffff', $manifest['theme_color']);

        $icons = collect($manifest['icons']);
        foreach (['192x192', '512x512'] as $size) {
            $this->assertTrue($icons->contains(fn (array $icon) => $icon['sizes'] === $size && $icon['purpose'] === 'any'));
        }
        $this->assertTrue($icons->contains(fn (array $icon) => $icon['purpose'] === 'maskable'));
    }

    public static function manifestIcons(): array
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../../public/manifest.webmanifest'), true, flags: JSON_THROW_ON_ERROR);

        return array_combine(
            array_column($manifest['icons'], 'src'),
            array_map(fn (array $icon) => [$icon], $manifest['icons']),
        );
    }

    #[DataProvider('manifestIcons')]
    public function test_manifest_icon_exists_and_matches_its_declared_format_and_dimensions(array $icon): void
    {
        $path = public_path(ltrim($icon['src'], '/'));
        $this->assertFileExists($path);
        $image = getimagesize($path);
        $this->assertNotFalse($image);
        $this->assertSame($icon['type'], $image['mime']);
        $this->assertSame($icon['sizes'], $image[0].'x'.$image[1], $icon['src'].' dimensions differ from the manifest.');
    }

    public function test_apple_touch_icon_is_a_180_pixel_png(): void
    {
        $path = public_path('apple-touch-icon.png');
        $this->assertFileExists($path);
        $image = getimagesize($path);
        $this->assertNotFalse($image);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame([180, 180], [$image[0], $image[1]]);
    }
}
