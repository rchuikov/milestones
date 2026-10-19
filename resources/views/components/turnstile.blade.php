<div data-turnstile-container>
    <div
        class="cf-turnstile max-sm:w-full max-sm:data-[size=compact]:flex max-sm:data-[size=compact]:justify-center"
        data-sitekey="{{ config('services.turnstile.site_key') }}"
        data-size="flexible"
        data-theme="light"
        data-language="{{ app()->getLocale() === 'sr_Latn' ? 'sr' : app()->getLocale() }}"
        data-callback="turnstileSuccess"
        data-expired-callback="turnstileBlocked"
        data-error-callback="turnstileError"
        data-timeout-callback="turnstileBlocked"
    ></div>
</div>

@error('cf-turnstile-response')
<div class="mb-4 text-sm text-red-600">
    {{ $message }}
</div>
@enderror

<div
    data-turnstile-warning
    role="alert"
    class="mt-3 hidden rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
>
    <p class="font-medium">
        {{ __('Security verification is unavailable.') }}
    </p>

    <p class="mt-1">
        {{ __('Please check your connection or browser extensions and try again.') }}
    </p>

    <button
        type="button"
        data-turnstile-retry
        class="mt-3 cursor-pointer rounded-md bg-amber-700 px-4 py-2 font-medium text-white hover:bg-amber-800"
    >
        {{ __('Retry') }}
    </button>
</div>

@once
    @push('scripts')
        <script>
            const turnstileWidget = document.querySelector('.cf-turnstile');
            const turnstileContainer = turnstileWidget.closest('[data-turnstile-container]');
            const turnstileForm = turnstileWidget.closest('form');
            const turnstileWarning = turnstileForm.querySelector('[data-turnstile-warning]');
            const turnstileRetry = turnstileForm.querySelector('[data-turnstile-retry]');

            let turnstileScriptLoaded = false;
            let turnstileId = null;
            let turnstileConfiguration = null;

            function renderTurnstile() {
                const mobile = window.matchMedia('(width < 640px)').matches;
                const size = mobile && turnstileContainer.getBoundingClientRect().width < 300
                    ? 'compact'
                    : 'flexible';
                const theme = 'light';
                const configuration = `${size}:${theme}`;

                if (configuration === turnstileConfiguration) {
                    return;
                }

                if (turnstileId !== null) {
                    setSubmitBlocked(true);
                    window.turnstile.remove(turnstileId);
                }

                turnstileWidget.dataset.size = size;
                turnstileWidget.dataset.theme = theme;
                turnstileConfiguration = configuration;
                turnstileId = window.turnstile.render(turnstileWidget, { size, theme });
            }

            new ResizeObserver(() => {
                if (turnstileScriptLoaded) {
                    renderTurnstile();
                }
            }).observe(turnstileContainer);

            function showTurnstileWarning() {
                turnstileWarning.classList.remove('hidden');
            }

            function hideTurnstileWarning() {
                turnstileWarning.classList.add('hidden');
            }

            function setSubmitBlocked(blocked) {
                turnstileForm.dataset.externallyBlocked = blocked ? '1' : '0';

                turnstileForm.dispatchEvent(new CustomEvent('submit-block-change', {
                    detail: { blocked },
                }));

                const button = turnstileForm.querySelector('button[type="submit"]');

                if (button && !button.matches('[data-cooldown-button]')) {
                    button.disabled = blocked;
                }
            }

            function turnstileScriptReady() {
                turnstileScriptLoaded = true;
                clearTimeout(turnstileLoadTimeout);
                hideTurnstileWarning();
                renderTurnstile();
            }

            function turnstileSuccess() {
                hideTurnstileWarning();
                setSubmitBlocked(false);
            }

            function turnstileBlocked() {
                setSubmitBlocked(true);
            }

            function turnstileError() {
                setSubmitBlocked(true);
                showTurnstileWarning();
            }

            const turnstileLoadTimeout = setTimeout(() => {
                if (!turnstileScriptLoaded) {
                    showTurnstileWarning();
                }
            }, 10000);

            turnstileRetry.addEventListener('click', () => {
                window.location.reload();
            });

            turnstileForm.addEventListener('submit', () => {
                setSubmitBlocked(true);
            });
        </script>

        <script
            src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"
            onload="turnstileScriptReady()"
            onerror="turnstileError()"
            async
            defer
        ></script>
    @endpush
@endonce
