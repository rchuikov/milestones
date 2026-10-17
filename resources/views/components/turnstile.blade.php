<div
    class="cf-turnstile"
    data-sitekey="{{ config('services.turnstile.site_key') }}"
    data-size="flexible"
    data-callback="turnstileSuccess"
    data-expired-callback="turnstileBlocked"
    data-error-callback="turnstileError"
    data-timeout-callback="turnstileBlocked"
></div>

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
        Security verification is unavailable.
    </p>

    <p class="mt-1">
        Please check your connection or browser extensions and try again.
    </p>

    <button
        type="button"
        data-turnstile-retry
        class="mt-3 cursor-pointer rounded-md bg-amber-700 px-4 py-2 font-medium text-white hover:bg-amber-800"
    >
        Retry
    </button>
</div>

@once
    @push('scripts')
        <script>
            const turnstileWidget = document.querySelector('.cf-turnstile');
            const turnstileForm = turnstileWidget.closest('form');
            const turnstileWarning = turnstileForm.querySelector('[data-turnstile-warning]');
            const turnstileRetry = turnstileForm.querySelector('[data-turnstile-retry]');

            let turnstileScriptLoaded = false;

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
            src="https://challenges.cloudflare.com/turnstile/v0/api.js"
            onload="turnstileScriptReady()"
            onerror="turnstileError()"
            async
            defer
        ></script>
    @endpush
@endonce
