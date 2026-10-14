import { Temporal } from '@js-temporal/polyfill';

function getMilestoneDuration(startedAt) {
    const timeZone = Temporal.Now.timeZoneId();

    const start = Temporal.Instant
        .from(startedAt)
        .toZonedDateTimeISO(timeZone);

    const now = Temporal.Now.zonedDateTimeISO(timeZone);

    if (Temporal.ZonedDateTime.compare(start, now) > 0) {
        return {
            duration: '00:00:00',
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

    parts.push(
        [
            duration.hours.toString().padStart(2, '0'),
            duration.minutes.toString().padStart(2, '0'),
            duration.seconds.toString().padStart(2, '0'),
        ].join(':')
    );

    return {
        duration: parts.join(' '),
        totalDays,
    };
}

function updateMilestoneTimers() {
    document.querySelectorAll('.milestone-timer').forEach(timer => {
        const result = getMilestoneDuration(timer.dataset.startedAt);

        timer.textContent = result.duration;

        const totalDays = timer.parentElement.querySelector('.milestone-total-days');

        totalDays.textContent = `(${result.totalDays} ${result.totalDays === 1 ? 'day' : 'days'})`;
    });
}

updateMilestoneTimers();

setInterval(updateMilestoneTimers, 1000);
