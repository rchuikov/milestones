
import assert from 'node:assert/strict';
import test from 'node:test';

import { JSDOM } from 'jsdom';
import { Temporal } from '@js-temporal/polyfill';

test('milestone timers render through Chrono', async () => {
    const now = Temporal.Now.instant();
    const startedAt = now.subtract({ hours: 48 }).toString();
    const futureAt = now.add({ hours: 240 }).toString();

    const dom = new JSDOM(`
        <!doctype html>
        <html lang="ru">
        <body>
            <div>
                <div class="milestone-duration hidden"></div>
                <div class="milestone-timer" data-started-at="${startedAt}"></div>
                <span class="milestone-total-days"></span>
            </div>
            <div>
                <div class="milestone-duration hidden"></div>
                <div class="milestone-timer" data-started-at="${futureAt}"></div>
                <span class="milestone-total-days"></span>
            </div>
        </body>
        </html>
    `);

    const originalDocument = globalThis.document;
    const originalWindow = globalThis.window;
    const originalSetInterval = globalThis.setInterval;
    const originalClearInterval = globalThis.clearInterval;

    const intervals = new Map();
    let nextIntervalId = 0;

    try {
        globalThis.document = dom.window.document;
        globalThis.window = dom.window;

        globalThis.setInterval = (callback, delay) => {
            assert.equal(delay, 1000);

            const id = ++nextIntervalId;
            intervals.set(id, callback);

            return id;
        };

        globalThis.clearInterval = id => {
            intervals.delete(id);
        };

        await import('../../resources/js/timer.js');

        const [pastTimer, futureTimer] =
            document.querySelectorAll('.milestone-timer');

        const pastContainer = pastTimer.parentElement;
        const futureContainer = futureTimer.parentElement;

        assert.match(pastTimer.textContent, /^\d{2}:\d{2}:\d{2}$/);

        assert.match(
            pastContainer.querySelector('.milestone-duration').textContent,
            /дн/,
        );

        assert.equal(
            pastContainer.querySelector('.milestone-duration')
                .classList.contains('hidden'),
            false,
        );

        assert.match(
            pastContainer.querySelector('.milestone-total-days').textContent,
            /^\(\d+ дня?\)$/,
        );

        assert.equal(futureTimer.textContent, '00:00:00');

        assert.equal(
            futureContainer.querySelector('.milestone-duration')
                .classList.contains('hidden'),
            true,
        );

        assert.equal(
            futureContainer.querySelector('.milestone-total-days').textContent,
            '(0 дней)',
        );

        // One shared Chrono scheduler and one date-change checker.
        assert.equal(intervals.size, 2);

        for (const callback of intervals.values()) {
            callback();
        }

        assert.match(pastTimer.textContent, /^\d{2}:\d{2}:\d{2}$/);
    } finally {
        globalThis.document = originalDocument;
        globalThis.window = originalWindow;
        globalThis.setInterval = originalSetInterval;
        globalThis.clearInterval = originalClearInterval;

        dom.window.close();
    }
});
