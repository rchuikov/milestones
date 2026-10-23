import { Temporal } from '@js-temporal/polyfill';

import {
    createTimer,
    render,
    formatUnit,
} from '@rchuikov/chrono';

const language = document.documentElement.lang;
const locale = ['en', 'ru', 'sr-Latn'].includes(language)
    ? language
    : language.startsWith('sr') ? 'sr-Latn' : 'en';

const timeZone = Temporal.Now.timeZoneId();

function getCalendarDays(startedAt) {
    const start = Temporal.Instant.from(startedAt)
        .toZonedDateTimeISO(timeZone)
        .toPlainDate();

    const today = Temporal.Now.plainDateISO(timeZone);

    return Math.max(0, start.until(today, {
        largestUnit: 'days',
    }).days);
}

document.querySelectorAll('.milestone-timer').forEach(element => {
    const container = element.parentElement;
    const durationElement = container.querySelector('.milestone-duration');
    const totalDaysElement = container.querySelector('.milestone-total-days');

    createTimer({
        startedAt: element.dataset.startedAt,
        timeZone,
        onTick: duration => {
            render(duration, '{time}', {
                locale,
                element,
            });

            if (durationElement) {
                render(duration, '{duration}', {
                    locale,
                    element: durationElement,
                });

                durationElement.classList.toggle(
                    'hidden',
                    !durationElement.textContent.trim(),
                );
            }

            if (totalDaysElement) {
                const days = getCalendarDays(element.dataset.startedAt);

                totalDaysElement.textContent =
                    `(${formatUnit('day', days, locale)})`;
            }
        },
    }).start();
});

let currentDate = Temporal.Now.plainDateISO(timeZone).toString();

setInterval(() => {
    const today = Temporal.Now.plainDateISO(timeZone).toString();

    if (today !== currentDate) {
        window.location.reload();
    }
}, 1000);
