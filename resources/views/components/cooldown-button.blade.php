@props([
    'storagePrefix' => null,
    'label',
    'countdownLabel' => null,
    'seconds' => 60,
    'remainingSeconds' => null,
    'restart' => false,
])

@php
    $countdownLabel ??= $label;
    $remaining = max(0, (int) $remainingSeconds);
@endphp

<button
    type="submit"
    @disabled($remaining > 0)
    {{ $attributes->merge([
        'class' => 'inline-flex cursor-pointer items-center justify-center rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600',
    ]) }}
    data-cooldown-button
    data-storage-prefix="{{ $storagePrefix }}"
    data-seconds="{{ $seconds }}"
    data-label="{{ $label }}"
    data-countdown-label="{{ $countdownLabel }}"
    data-remaining-seconds="{{ $remainingSeconds }}"
    data-restart="{{ $restart ? '1' : '0' }}"
>
    {{ $remaining > 0
        ? $countdownLabel.' '.sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60)
        : $label }}
</button>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-cooldown-button]').forEach(button => {
                const label = button.dataset.label;
                const countdownLabel = button.dataset.countdownLabel;
                const serverControlled = button.dataset.remainingSeconds !== '';
                const deadlineKey = `${button.dataset.storagePrefix}_available_at`;
                const serverDeadline = Date.now() + Number(button.dataset.remainingSeconds) * 1000;

                const form = button.closest('form');
                let externallyBlocked = form?.dataset.externallyBlocked === '1';

                if (! serverControlled && button.dataset.restart === '1') {
                    sessionStorage.setItem(
                        deadlineKey,
                        String(Date.now() + Number(button.dataset.seconds) * 1000),
                    );
                }

                function updateButton() {
                    const deadline = serverControlled
                        ? serverDeadline
                        : Number(sessionStorage.getItem(deadlineKey));

                    const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
                    const cooldownActive = remaining > 0;

                    if (! cooldownActive) {
                        if (! serverControlled) {
                            sessionStorage.removeItem(deadlineKey);
                        }

                        button.textContent = label;
                    } else {
                        button.textContent =
                            `${countdownLabel} `
                            + `${String(Math.floor(remaining / 60)).padStart(2, '0')}:`
                            + `${String(remaining % 60).padStart(2, '0')}`;
                    }

                    button.disabled = cooldownActive || externallyBlocked;
                }

                form?.addEventListener('submit-block-change', event => {
                    externallyBlocked = event.detail.blocked;
                    updateButton();
                });

                updateButton();
                setInterval(updateButton, 1000);
            });
        </script>
    @endpush
@endonce
