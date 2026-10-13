import { Temporal } from '@js-temporal/polyfill';

function getMilestoneDuration(startedAt) {
    const timeZone = Temporal.Now.timeZoneId();

    const start = Temporal.Instant
        .from(startedAt)
        .toZonedDateTimeISO(timeZone);

    const now = Temporal.Now.zonedDateTimeISO(timeZone);

    if (Temporal.ZonedDateTime.compare(start, now) > 0) {
        return {
            duration: '',
            time: '00:00:00',
            totalDays: 0,
        };
    }

    const duration = now.since(start, {
        largestUnit: 'years',
        smallestUnit: 'seconds',
        roundingMode: 'trunc',
    });

    const totalDays = now.since(start, {
        largestUnit: 'days',
        smallestUnit: 'days',
        roundingMode: 'trunc',
    }).days;

    const parts = [];

    if (duration.years) {
        parts.push(
            `${duration.years} ${duration.years === 1 ? 'year' : 'years'}`
        );
    }

    if (duration.months) {
        parts.push(
            `${duration.months} ${duration.months === 1 ? 'month' : 'months'}`
        );
    }

    if (duration.days) {
        parts.push(
            `${duration.days} ${duration.days === 1 ? 'day' : 'days'}`
        );
    }

    const time = [
        duration.hours.toString().padStart(2, '0'),
        duration.minutes.toString().padStart(2, '0'),
        duration.seconds.toString().padStart(2, '0'),
    ].join(':');

    return {
        duration: parts.join(' '),
        time,
        totalDays,
    };
}

function updateMilestoneTimers() {
    document.querySelectorAll('.milestone-timer').forEach(timer => {
        const result = getMilestoneDuration(timer.dataset.startedAt);
        const container = timer.parentElement;

        timer.textContent = result.time;

        const duration = container.querySelector('.milestone-duration');

        if (duration) {
            duration.textContent = result.duration;
            duration.classList.toggle('hidden', ! result.duration);
        }

        const totalDays = container.querySelector('.milestone-total-days');

        if (totalDays) {
            totalDays.textContent =
                `(${result.totalDays} ${result.totalDays === 1 ? 'day' : 'days'})`;
        }
    });
}

let currentDate = Temporal.Now.plainDateISO().toString();

function reloadOnDateChange() {
    const today = Temporal.Now.plainDateISO().toString();

    if (today !== currentDate) {
        window.location.reload();
    }
}

updateMilestoneTimers();

setInterval(() => {
    updateMilestoneTimers();
    reloadOnDateChange();
}, 1000);
