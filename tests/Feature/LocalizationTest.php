<?php

namespace Tests\Feature;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public static function guestPages(): array
    {
        return [
            ['/login', 'Login', 'Войти', 'Prijava'],
            ['/register', 'Register', 'Регистрация', 'Registracija'],
            ['/forgot-password', 'Forgot password', 'Восстановление пароля', 'Zaboravljena lozinka'],
            ['/reset-password/token', 'Reset password', 'Сбросить пароль', 'Resetuj lozinku'],
        ];
    }

    #[DataProvider('guestPages')]
    public function test_guest_pages_follow_session_language(string $url, string $english, string $russian, string $serbian): void
    {
        $this->withSession(['locale' => 'ru'])->get($url)->assertOk()
            ->assertSee('lang="ru"', false)->assertSee($russian)
            ->assertSee('English')->assertSee('Русский');
        $this->withSession(['locale' => 'en'])->get($url)->assertOk()
            ->assertSee('lang="en"', false)->assertSee($english);
        $this->withSession(['locale' => 'sr_Latn'])->get($url)->assertOk()
            ->assertSee('lang="sr-Latn"', false)->assertSee($serbian)->assertSee('Srpski');
    }

    public function test_authenticated_pages_use_user_language_and_settings_save_it(): void
    {
        $user = User::factory()->create(['locale' => Locale::Russian]);
        $this->actingAs($user)->withSession(['locale' => 'en']);
        foreach (['/settings' => 'Настройки', '/account' => 'Аккаунт', '/' => 'Мои вехи', '/milestones/create' => 'Создать веху'] as $url => $text) {
            $this->get($url)->assertOk()->assertSee('lang="ru"', false)->assertSee($text);
        }
        $this->from('/settings')->patch('/settings', ['name' => 'Новое имя', 'locale' => 'en'])
            ->assertRedirect('/settings')->assertSessionHas('flash.message', 'Settings updated.');
        $this->assertSame(Locale::English, $user->fresh()->locale);
        $this->get('/settings')->assertSee('lang="en"', false)->assertSee('Manage your personal settings.');
        $this->patch('/settings', ['name' => 'Новое имя', 'locale' => 'ru'])
            ->assertSessionHas('flash.message', 'Настройки сохранены.');
    }

    public function test_validation_login_limits_and_turnstile_errors_are_translated(): void
    {
        $this->travelTo(now()->startOfSecond());
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);
        $this->withSession(['locale' => 'ru'])->get('/login')->assertOk()
            ->assertSee('data-language="ru"', false);
        $this->post('/login', ['email' => 'not-an-email'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertStringNotContainsString('email field', session('errors')->first('email'));
        $data = ['email' => 'missing@example.com', 'password' => 'wrong', 'cf-turnstile-response' => 'token'];
        $this->post('/login', $data)->assertSessionHasErrors(['email' => 'Неверная почта или пароль. Осталось попыток: 4']);
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', $data);
        }
        $this->get('/login')->assertSee('Слишком много попыток входа. Попробуйте позже.')
            ->assertSee('Повторите через 01:00');
    }

    public function test_turnstile_errors_are_translated(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false])]);
        $this->withSession(['locale' => 'ru'])->post('/login', [
            'email' => 'missing@example.com', 'password' => 'wrong', 'cf-turnstile-response' => 'token',
        ])->assertSessionHasErrors(['cf-turnstile-response' => 'Проверка безопасности не пройдена. Попробуйте ещё раз.']);
    }

    public function test_milestones_history_flash_and_timer_dictionary_are_translated(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $user = User::factory()->create(['locale' => Locale::Russian]);
        $milestone = $user->milestones()->create(['name' => 'Тест']);
        $milestone->periods()->create(['started_at' => now()->subDays(21)]);
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('История')
            ->assertSee('3 недели')
            ->assertSee('Удалить эту веху?')
            ->assertSee('milestone-timer', false);
        $this->get('/milestones/'.$milestone->id.'/history')->assertOk()
            ->assertSee('Текущий период')->assertSee('21 день')->assertDontSee('21 days');
        $this->delete('/milestones/'.$milestone->id)->assertRedirect('/')
            ->assertSessionHas('flash.message', 'Веха удалена.');
    }

    public function test_verification_page_and_maintenance_are_translated(): void
    {
        $user = User::factory()->unverified()->create(['locale' => Locale::Russian]);
        $this->actingAs($user)->get('/email/verify')->assertOk()->assertSee('Подтвердите почту')
            ->assertSee('Отправить письмо ещё раз');
        file_put_contents(storage_path('framework/app-maintenance'), '');
        $this->get('/')->assertStatus(503)->assertSee('lang="ru"', false)
            ->assertSee('Мы скоро вернёмся!')->assertSee('плановые технические работы');
    }

    public function test_serbian_locale_can_be_selected_saved_and_used_for_pages(): void
    {
        $this->from('/login')->post('/locale', ['locale' => 'sr_Latn'])
            ->assertRedirect('/login')->assertSessionHas('locale', 'sr_Latn');
        $this->get('/login')->assertOk()->assertSee('Prijava')->assertSee('data-language="sr"', false);

        $user = User::factory()->create();
        $this->actingAs($user)->from('/settings')->patch('/settings', ['name' => 'Novo ime', 'locale' => 'sr_Latn'])
            ->assertRedirect('/settings')->assertSessionHasNoErrors()
            ->assertSessionHas('flash.message', 'Podešavanja su sačuvana.');
        $this->assertSame(Locale::Serbian, $user->fresh()->locale);

        foreach (['/settings' => 'Podešavanja', '/account' => 'Nalog', '/' => 'Moje prekretnice', '/milestones/create' => 'Posebni datumi'] as $url => $text) {
            $this->get($url)->assertOk()->assertSee('lang="sr-Latn"', false)->assertSee($text);
        }
        $milestone = $user->milestones()->create(['name' => 'Test']);
        $milestone->periods()->create(['started_at' => now()->subDays(21)]);
        $this->get('/milestones/'.$milestone->id.'/history')->assertOk()
            ->assertSee('Trenutni period')->assertSee('21 dan');
        $this->delete('/milestones/'.$milestone->id)->assertSessionHas('flash.message', 'Prekretnica je obrisana.');
    }

    public function test_serbian_catalog_is_complete_and_uses_latin_script(): void
    {
        $russian = json_decode(file_get_contents(lang_path('ru.json')), true, flags: JSON_THROW_ON_ERROR);
        $serbian = json_decode(file_get_contents(lang_path('sr_Latn.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], array_diff_key($russian, $serbian));
        foreach ($serbian as $translation) {
            $this->assertDoesNotMatchRegularExpression('/[\x{0400}-\x{04FF}]/u', $translation);
        }
    }

    public function test_serbian_standard_validation_and_authentication_errors_are_translated(): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);
        $this->withSession(['locale' => 'sr_Latn'])->post('/login', [
            'email' => 'missing@example.com', 'password' => 'wrong', 'cf-turnstile-response' => 'token',
        ])->assertSessionHasErrors(['email' => 'Pogrešna imejl adresa ili lozinka. Preostalo pokušaja: 4']);
        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertSame(__('validation.required', ['attribute' => __('validation.attributes.name')], 'sr_Latn'), session('errors')->first('name'));
    }

    public static function serbianCounts(): array
    {
        return [[0, 'dana', 'meseci', 'godina'], [1, 'dan', 'mesec', 'godina'], [2, 'dana', 'meseca', 'godine'], [5, 'dana', 'meseci', 'godina'], [11, 'dana', 'meseci', 'godina'], [21, 'dan', 'mesec', 'godina'], [22, 'dana', 'meseca', 'godine'], [25, 'dana', 'meseci', 'godina']];
    }

    #[DataProvider('serbianCounts')]
    public function test_serbian_plural_forms(int $count, string $days, string $months, string $years): void
    {
        app()->setLocale('sr_Latn');
        $this->assertSame($count.' '.$days, trans_choice(':count day|:count days', $count));
        $this->assertSame($count.' '.$months, trans_choice(':count month|:count months', $count));
        $this->assertSame($count.' '.$years, trans_choice(':count year|:count years', $count));
    }

    public function test_unverified_user_can_change_locale_from_verification_notice(): void
    {
        $user = User::factory()->unverified()->create(['locale' => Locale::English]);
        $this->actingAs($user)->get('/email/verify')->assertOk()
            ->assertSee('action="'.route('locale.update').'"', false);
        $this->from('/email/verify')->post('/locale', ['locale' => 'ru'])
            ->assertRedirect('/email/verify')->assertSessionHasNoErrors();
        $this->assertSame(Locale::Russian, $user->fresh()->locale);
        $this->get('/email/verify')->assertOk()->assertSee('Подтвердите почту');
    }

    public static function localeSwitcherUsers(): array
    {
        return ['guest' => [false], 'authenticated user' => [true]];
    }

    #[DataProvider('localeSwitcherUsers')]
    public function test_locale_switching_remains_available_during_maintenance(bool $authenticated): void
    {
        $user = $authenticated ? User::factory()->create(['locale' => Locale::English]) : null;
        if ($user) {
            $this->actingAs($user);
        }
        file_put_contents(storage_path('framework/app-maintenance'), '');
        $this->from('/')->post('/locale', ['locale' => 'sr_Latn'])
            ->assertRedirect('/')->assertSessionHasNoErrors();
        if ($user) {
            $this->assertSame(Locale::Serbian, $user->fresh()->locale);
        } else {
            $this->assertSame('sr_Latn', session('locale'));
        }
    }

    public static function loginLocales(): array
    {
        return [
            'no guest choice preserves account language' => [null, 'ru', 'ru'],
            'explicit English replaces Russian' => ['en', 'ru', 'en'],
            'explicit Russian replaces English' => ['ru', 'en', 'ru'],
            'explicit Serbian replaces English' => ['sr_Latn', 'en', 'sr_Latn'],
        ];
    }

    #[DataProvider('loginLocales')]
    public function test_password_login_applies_only_an_explicit_guest_locale(?string $selectedLocale, string $storedLocale, string $expectedLocale): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);
        $user = User::factory()->create(['locale' => $storedLocale]);
        if ($selectedLocale !== null) {
            $this->from('/login')->post('/locale', ['locale' => $selectedLocale])->assertRedirect('/login');
        }

        $this->withSession(['url.intended' => url('/settings')])->post('/login', [
            'email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'token',
        ])->assertRedirect('/settings')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Locale::from($expectedLocale), $user->fresh()->locale);
        $this->get('/settings')->assertOk()
            ->assertSee('lang="'.str_replace('_', '-', $expectedLocale).'"', false);
        $this->post('/logout')->assertRedirect('/')->assertSessionHas('locale', $expectedLocale);
    }

    public static function unsuccessfulLogins(): array
    {
        return ['wrong password' => [false], 'correct password while blocked' => [true]];
    }

    #[DataProvider('unsuccessfulLogins')]
    public function test_unsuccessful_login_does_not_change_account_locale(bool $blocked): void
    {
        config(['services.turnstile.secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);
        $user = User::factory()->create(['locale' => Locale::English]);
        if ($blocked) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                RateLimiter::hit('login:'.$user->email.'|127.0.0.1');
            }
        }

        $response = $this->withSession(['locale' => 'sr_Latn'])->from('/login')->post('/login', [
            'email' => $user->email, 'password' => $blocked ? 'password' : 'wrong', 'cf-turnstile-response' => 'token',
        ])->assertRedirect('/login');
        if ($blocked) {
            $response->assertSessionHas('loginCooldown');
        } else {
            $response->assertSessionHasErrors('email');
        }
        $this->assertGuest();
        $this->assertSame(Locale::English, $user->fresh()->locale);
    }

    public static function mailLocales(): array
    {
        return [['en', 'ru'], ['ru', 'en'], ['sr_Latn', 'en'], ['en', 'sr_Latn']];
    }

    #[DataProvider('mailLocales')]
    public function test_emails_use_recipient_language_and_restore_application_locale(string $recipientLocale, string $applicationLocale): void
    {
        app()->setLocale($applicationLocale);
        $user = User::factory()->create(['locale' => $recipientLocale]);
        $this->mock(MailChannel::class, function ($mock) use ($recipientLocale) {
            $mock->shouldReceive('send')->twice()->andReturnUsing(function ($recipient, $notification) use ($recipientLocale) {
                $this->assertSame($recipientLocale, app()->getLocale());
                $mail = $notification->toMail($recipient);
                $key = $notification instanceof VerifyEmail ? 'Verify your MyMilestones email address' : 'Reset your MyMilestones password';
                $this->assertSame(__($key, [], $recipientLocale), $mail->subject);
                $this->assertStringContainsString(__('All rights reserved.', [], $recipientLocale), (string) $mail->render());
                $this->assertSame(match ($recipientLocale) {
                    'ru' => "С уважением,  \nКоманда MyMilestones",
                    'sr_Latn' => "Srdačan pozdrav,  \nMyMilestones tim",
                    default => "Best regards,  \nThe MyMilestones Team",
                }, $mail->salutation);
            });
        });
        $user->notify(new VerifyEmail);
        $user->notify(new ResetPassword('test-token'));
        $this->assertSame($applicationLocale, app()->getLocale());
    }
}
