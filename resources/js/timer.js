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

const timers = new Map();

function getCalendarDays(startedAt) {
    const start = Temporal.Instant.from(startedAt)
        .toZonedDateTimeISO(timeZone)
        .toPlainDate();

    const today = Temporal.Now.plainDateISO(timeZone);

    return Math.max(0, start.until(today, {
        largestUnit: 'days',
    }).days);
}

function initMilestoneTimers(root = document) {
    root.querySelectorAll('.milestone-timer').forEach(element => {
        if (timers.has(element)) {
            return;
        }

        const container = element.parentElement;
        const durationElement = container.querySelector('.milestone-duration');
        const totalDaysElement = container.querySelector('.milestone-total-days');

        const timer = createTimer({
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
        });

        timers.set(element, timer);
        timer.start();
    });
}

function disposeMilestoneTimers(root) {
    root.querySelectorAll('.milestone-timer').forEach(element => {
        const timer = timers.get(element);

        if (timer) {
            timer.dispose();
            timers.delete(element);
        }
    });
}

export function refreshMilestoneTimer(oldCard, newCard) {
    disposeMilestoneTimers(oldCard);
    oldCard.replaceWith(newCard);
    initMilestoneTimers(newCard);
}

initMilestoneTimers();

let currentDate = Temporal.Now.plainDateISO(timeZone).toString();

setInterval(() => {
    const today = Temporal.Now.plainDateISO(timeZone).toString();

    if (today !== currentDate) {
        window.location.reload();
    }
}, 1000);
