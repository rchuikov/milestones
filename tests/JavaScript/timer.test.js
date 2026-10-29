
import assert from 'node:assert/strict';
import test from 'node:test';

import { JSDOM } from 'jsdom';
import { Temporal } from '@js-temporal/polyfill';

test('milestone timers render through Chrono and survive AJAX card replacements', async () => {
    const now = Temporal.Now.instant();
    const startedAt = now.subtract({ hours: 48 }).toString();
    const futureAt = now.add({ hours: 240 }).toString();

    const dom = new JSDOM(`
        <!doctype html>
        <html lang="ru">
        <body>
            <div data-milestone-id="1">
                <div class="milestone-duration hidden"></div>
                <div class="milestone-timer" data-started-at="${startedAt}"></div>
                <span class="milestone-total-days"></span>
            </div>
            <div data-milestone-id="2">
                <div class="milestone-duration hidden"></div>
                <div class="milestone-timer" data-started-at="${futureAt}"></div>
                <span class="milestone-total-days"></span>
            </div>
        </body>
        </html>
    `);

    let clock = now;
    const originalInstant = Temporal.Now.instant;
    const originalDocument = globalThis.document;
    const originalWindow = globalThis.window;
    const originalSetInterval = globalThis.setInterval;
    const originalClearInterval = globalThis.clearInterval;

    const intervals = new Map();
    let nextIntervalId = 0;

    try {
        Temporal.Now.instant = () => clock;
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

        const { refreshMilestoneTimer } = await import('../../resources/js/timer.js');

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

        const activeCard = () => {
            const card = document.createElement('div');
            card.dataset.milestoneId = '1';
            card.innerHTML = `
                <div>
                    <div class="milestone-duration hidden"></div>
                    <div class="milestone-timer" data-started-at="${Temporal.Now.instant().toString()}"></div>
                    <span class="milestone-total-days"></span>
                </div>
            `;
            return card;
        };

        // Reset replaces an active timer and renders its new start immediately.
        const oldText = pastTimer.textContent;
        const resetCard = activeCard();
        refreshMilestoneTimer(pastContainer, resetCard);
        assert.equal(document.querySelector('[data-milestone-id="1"]'), resetCard);
        const resetTimer = resetCard.querySelector('.milestone-timer');
        assert.equal(resetTimer.textContent, '00:00:00');
        assert.equal(resetCard.querySelector('.milestone-duration').classList.contains('hidden'), true);
        assert.equal(resetCard.querySelector('.milestone-total-days').textContent, '(0 дней)');

        // Detached timers must stop updating; other cards must keep ticking.
        clock = clock.add({ seconds: 1 });
        futureContainer.querySelector('.milestone-total-days').textContent = 'needs tick';
        for (const callback of intervals.values()) callback();
        assert.equal(pastTimer.textContent, oldText);
        assert.equal(resetTimer.textContent, '00:00:01');
        assert.equal(futureContainer.querySelector('.milestone-total-days').textContent, '(0 дней)');
        assert.equal(intervals.size, 2);

        const stoppedCard = document.createElement('div');
        stoppedCard.dataset.milestoneId = '1';
        stoppedCard.textContent = 'Stopped';
        refreshMilestoneTimer(resetCard, stoppedCard);
        clock = clock.add({ seconds: 1 });
        for (const callback of intervals.values()) callback();
        assert.equal(resetTimer.textContent, '00:00:01');
        assert.equal(stoppedCard.querySelector('.milestone-timer'), null);

        // Stopping the last timer releases Chrono's scheduler, not the date checker.
        refreshMilestoneTimer(futureContainer, document.createElement('div'));
        assert.equal(intervals.size, 1);

        const startedCard = activeCard();
        refreshMilestoneTimer(stoppedCard, startedCard);
        assert.equal(startedCard.querySelector('.milestone-timer').textContent, '00:00:00');
        assert.equal(intervals.size, 2);

        // Repeated resets must not leave timers or schedulers behind.
        let currentCard = startedCard;
        for (let i = 0; i < 3; i++) {
            const nextCard = activeCard();
            refreshMilestoneTimer(currentCard, nextCard);
            currentCard = nextCard;
            assert.equal(intervals.size, 2);
        }
        refreshMilestoneTimer(currentCard, document.createElement('div'));
        assert.equal(intervals.size, 1);
    } finally {
        Temporal.Now.instant = originalInstant;
        globalThis.document = originalDocument;
        globalThis.window = originalWindow;
        globalThis.setInterval = originalSetInterval;
        globalThis.clearInterval = originalClearInterval;

        dom.window.close();
    }
});
