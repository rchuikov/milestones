import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { Temporal } from '@js-temporal/polyfill';

const source = await readFile(new URL('../../resources/js/timer.js', import.meta.url), 'utf8');

// Run the actual browser module with controlled time and a minimal DOM.
async function loadTimer({ now = '2026-10-15T12:00:00Z', timeZone = 'UTC', timers = [], locale = 'en' } = {}) {
    let currentInstant = Temporal.Instant.from(now);
    let interval;
    let reloads = 0;
    const unitLabels = locale === 'sr-Latn'
        ? { day: ['dan', 'dana', 'dana'], month: ['mesec', 'meseca', 'meseci'], year: ['godina', 'godine', 'godina'] }
        : locale === 'ru'
        ? { day: ['день', 'дня', 'дней'], month: ['месяц', 'месяца', 'месяцев'], year: ['год', 'года', 'лет'] }
        : { day: ['day', 'days', 'days'], month: ['month', 'months', 'months'], year: ['year', 'years', 'years'] };
    const milestoneTranslations = Object.fromEntries(Object.entries(unitLabels).map(([unit, forms]) => [unit, {
        one: `:count ${forms[0]}`,
        few: `:count ${forms[1]}`,
        many: `:count ${forms[2]}`,
        other: `:count ${forms[2]}`,
    }]));
    const context = vm.createContext({
        document: { documentElement: { lang: locale }, querySelectorAll: () => timers },
        window: { milestoneTranslations, location: { reload: () => { reloads++; } } },
        setInterval: (callback, delay) => {
            assert.equal(delay, 1000);
            interval = callback;
        },
    });
    const temporal = new vm.SyntheticModule(['Temporal'], function () {
        this.setExport('Temporal', {
            ...Temporal,
            Now: {
                timeZoneId: () => timeZone,
                zonedDateTimeISO: () => currentInstant.toZonedDateTimeISO(timeZone),
                plainDateISO: () => currentInstant.toZonedDateTimeISO(timeZone).toPlainDate(),
            },
        });
    }, { context });
    const module = new vm.SourceTextModule(source + '\nexport { getMilestoneDuration };', { context });
    await module.link(specifier => {
        assert.equal(specifier, '@js-temporal/polyfill');
        return temporal;
    });
    await module.evaluate();

    return {
        duration: startedAt => JSON.parse(JSON.stringify(module.namespace.getMilestoneDuration(startedAt))),
        tick: instant => {
            currentInstant = Temporal.Instant.from(instant);
            interval();
        },
        get reloads() { return reloads; },
    };
}

for (const [name, startedAt, expected] of [
    ['zero duration', '2026-10-15T12:00:00Z', { duration: '', time: '00:00:00', totalDays: 0 }],
    ['future date', '2026-10-16T12:00:00Z', { duration: '', time: '00:00:00', totalDays: 0 }],
    ['hours minutes and seconds', '2026-10-15T10:57:56Z', { duration: '', time: '01:02:04', totalDays: 0 }],
    ['singular calendar units', '2025-09-14T11:00:00Z', { duration: '1 year 1 month 1 day', time: '01:00:00', totalDays: 396 }],
    ['plural calendar units', '2024-08-13T12:00:00Z', { duration: '2 years 2 months 2 days', time: '00:00:00', totalDays: 793 }],
    ['equivalent timestamp offset', '2026-10-15T14:00:00+02:00', { duration: '', time: '00:00:00', totalDays: 0 }],
]) {
    test(name, async () => {
        const timer = await loadTimer();
        assert.deepEqual(timer.duration(startedAt), expected);
    });
}

test('calendar day across daylight-saving change is not a fixed 24 hours', async () => {
    const timer = await loadTimer({ now: '2026-03-29T10:00:00Z', timeZone: 'Europe/Belgrade' });
    assert.deepEqual(timer.duration('2026-03-28T11:00:00Z'), { duration: '1 day', time: '00:00:00', totalDays: 1 });
});

test('initial render and interval update all counters and duration visibility', async () => {
    let hidden;
    const duration = { textContent: '', classList: { toggle: (name, value) => {
        assert.equal(name, 'hidden');
        hidden = value;
    } } };
    const days = { textContent: '' };
    const element = {
        dataset: { startedAt: '2026-10-15T11:59:59Z' },
        textContent: '',
        parentElement: { querySelector: selector => selector === '.milestone-duration' ? duration : days },
    };
    const timer = await loadTimer({ timers: [element] });
    assert.equal(element.textContent, '00:00:01');
    assert.equal(hidden, true);
    assert.equal(days.textContent, '(0 days)');
    timer.tick('2026-10-16T12:00:00Z');
    assert.equal(element.textContent, '00:00:01');
    assert.equal(duration.textContent, '1 day');
    assert.equal(hidden, false);
    assert.equal(days.textContent, '(1 day)');
});

test('missing optional display elements do not break updates', async () => {
    const element = { dataset: { startedAt: '2026-10-15T11:00:00Z' }, parentElement: { querySelector: () => null } };
    const timer = await loadTimer({ timers: [element] });
    assert.equal(element.textContent, '01:00:00');
    timer.tick('2026-10-15T12:00:01Z');
    assert.equal(element.textContent, '01:00:01');
});

test('page reloads only when the browser calendar date changes', async () => {
    const timer = await loadTimer({ now: '2026-10-15T21:59:58Z', timeZone: 'Europe/Belgrade' });
    timer.tick('2026-10-15T21:59:59Z');
    assert.equal(timer.reloads, 0);
    timer.tick('2026-10-15T22:00:00Z');
    assert.equal(timer.reloads, 1);
});

for (const [count, expected] of [[1, '1 день'], [2, '2 дня'], [5, '5 дней'], [11, '11 дней'], [21, '21 день'], [22, '22 дня'], [25, '25 дней']]) {
    test(`Russian day forms: ${count}`, async () => {
        const timer = await loadTimer({ locale: 'ru' });
        const start = Temporal.Instant.from('2026-10-15T12:00:00Z').subtract({ hours: count * 24 }).toString();
        assert.equal(timer.duration(start).duration, expected);
    });
}

for (const [startedAt, expected] of [
    ['2025-09-14T12:00:00Z', '1 год 1 месяц 1 день'],
    ['2024-08-13T12:00:00Z', '2 года 2 месяца 2 дня'],
    ['2021-05-10T12:00:00Z', '5 лет 5 месяцев 5 дней'],
]) {
    test(`Russian calendar units: ${expected}`, async () => {
        const timer = await loadTimer({ locale: 'ru' });
        assert.equal(timer.duration(startedAt).duration, expected);
    });
}

for (const [count, expected] of [[1, '1 dan'], [2, '2 dana'], [5, '5 dana'], [11, '11 dana'], [21, '21 dan'], [22, '22 dana'], [25, '25 dana']]) {
    test(`Serbian Latin day forms: ${count}`, async () => {
        const timer = await loadTimer({ locale: 'sr-Latn' });
        const start = Temporal.Instant.from('2026-10-15T12:00:00Z').subtract({ hours: count * 24 }).toString();
        assert.equal(timer.duration(start).duration, expected);
    });
}
