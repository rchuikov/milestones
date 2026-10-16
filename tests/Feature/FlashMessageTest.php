<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FlashMessageTest extends TestCase
{
    public static function flashTypes(): array
    {
        return [
            'success' => ['success', 'bg-green-50', 'status'],
            'info' => ['info', 'bg-sky-50', 'status'],
            'warning' => ['warning', 'bg-amber-50', 'alert'],
            'danger' => ['danger', 'bg-red-50', 'alert'],
        ];
    }

    #[DataProvider('flashTypes')]
    public function test_redirect_macros_display_escaped_messages_with_the_expected_style(
        string $type,
        string $background,
        string $role,
    ): void {
        $message = '<script>alert("flash")</script>';

        Route::middleware('web')->get('/_test/flash', fn () => redirect()->route('login')->{$type}($message));

        $this->get('/_test/flash')->assertRedirect(route('login'))
            ->assertSessionHas('flash', ['type' => $type, 'message' => $message])
            ->assertSessionMissing('status');

        $response = $this->get('/login')->assertOk()
            ->assertSee($message)
            ->assertDontSee($message, false)
            ->assertSee($background, false)
            ->assertSee('role="'.$role.'"', false);

        $this->assertMatchesRegularExpression('/<svg\b/', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), e($message)));

        $this->get('/login')->assertOk()->assertDontSee($message)->assertSessionMissing('flash');
    }

    public function test_zero_string_is_displayed(): void
    {
        $this->withSession(['flash' => ['type' => 'info', 'message' => '0']]);

        $this->blade('<x-flash-message />')
            ->assertSee('role="status"', false)
            ->assertSee('0');
    }

    public static function hiddenMessages(): array
    {
        return [
            'no flash' => [null],
            'empty text' => [['type' => 'success', 'message' => '']],
            'missing text' => [['type' => 'success']],
            'unknown type' => [['type' => 'unknown', 'message' => 'Hidden message']],
        ];
    }

    #[DataProvider('hiddenMessages')]
    public function test_absent_empty_or_unsupported_flash_is_not_rendered(?array $flash): void
    {
        $this->withSession(['flash' => $flash]);

        $this->assertSame('', trim((string) $this->blade('<x-flash-message />')));
    }
}
