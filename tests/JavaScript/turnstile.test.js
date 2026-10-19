import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const turnstileBlade = await readFile(new URL('../../resources/views/components/turnstile.blade.php', import.meta.url), 'utf8');
const cooldownBlade = await readFile(new URL('../../resources/views/components/cooldown-button.blade.php', import.meta.url), 'utf8');
const script = blade => blade.match(/<script>([\s\S]*?)<\/script>/)[1];

function render({ cooldown = false, remaining = 0, turnstileFirst = true, viewportWidth = 1280, containerWidth = 400, widgetWidth = 150 } = {}) {
    let now = 100000;
    let tick;
    let reloads = 0;
    let resized;
    const renders = [];
    const removals = [];
    const container = { getBoundingClientRect: () => ({ width: containerWidth }) };
    const widget = {
        dataset: {},
        closest: selector => selector === '[data-turnstile-container]' ? container : form,
        getBoundingClientRect: () => ({ width: widgetWidth }),
    };
    const timeouts = new Map();
    const listeners = {};
    const events = [];
    const warningClasses = new Set(['hidden']);
    const warning = {
        classList: {
            add(name) { warningClasses.add(name); },
            remove(name) { warningClasses.delete(name); },
        },
    };
    const retryListeners = {};
    const retry = { addEventListener(name, callback) { retryListeners[name] = callback; } };
    const form = {
        dataset: { externallyBlocked: '1' },
        addEventListener(name, callback) {
            (listeners[name] ??= []).push(callback);
        },
        dispatchEvent(event) {
            events.push(event);
            for (const callback of listeners[event.type] ?? []) callback(event);
        },
        querySelector(selector) {
            if (selector === '[data-turnstile-warning]') return warning;
            if (selector === '[data-turnstile-retry]') return retry;
            assert.equal(selector, 'button[type="submit"]');
            return button;
        },
    };
    const button = {
        disabled: true,
        textContent: 'Login',
        dataset: {
            label: 'Login', countdownLabel: 'Try again in', storagePrefix: 'login',
            remainingSeconds: String(remaining), restart: '0',
        },
        closest: () => form,
        matches(selector) {
            assert.equal(selector, '[data-cooldown-button]');
            return cooldown;
        },
    };
    const context = vm.createContext({
        document: {
            querySelector(selector) {
                assert.equal(selector, '.cf-turnstile');
                return widget;
            },
            querySelectorAll: () => [button],
        },
        CustomEvent: class {
            constructor(type, options) {
                this.type = type;
                this.detail = options.detail;
            }
        },
        Date: { now: () => now },
        setTimeout(callback, delay) {
            timeouts.set(1, { callback, at: now + delay });
            return 1;
        },
        clearTimeout(id) { timeouts.delete(id); },
        window: {
            location: { reload() { reloads++; } },
            matchMedia: () => ({ matches: viewportWidth < 640 }),
            turnstile: {
                render(element, options) {
                    assert.equal(element, widget);
                    renders.push({ size: options.size, theme: options.theme });
                    return renders.length;
                },
                remove(id) { removals.push(id); },
            },
        },
        ResizeObserver: class {
            constructor(callback) { resized = callback; }
            observe(element) { assert.equal(element, container); }
        },
        setInterval(callback) { tick = callback; },
    });
    const sources = cooldown
        ? (turnstileFirst ? [turnstileBlade, cooldownBlade] : [cooldownBlade, turnstileBlade])
        : [turnstileBlade];
    for (const source of sources) vm.runInContext(script(source), context);

    return {
        button, form, events, renders, removals,
        resize(width, availableWidth) {
            viewportWidth = width;
            containerWidth = availableWidth;
            resized();
        },
        get warningVisible() { return !warningClasses.has('hidden'); },
        get reloads() { return reloads; },
        retry() { retryListeners.click(); },
        callback(name) { vm.runInContext(name + '()', context); },
        submit() { form.dispatchEvent({ type: 'submit' }); },
        advance(seconds) {
            now += seconds * 1000;
            for (const [id, timeout] of timeouts) {
                if (timeout.at <= now) {
                    timeouts.delete(id);
                    timeout.callback();
                }
            }
            tick?.();
        },
    };
}

test('registration unlocks on success and locks again on submission', () => {
    const page = render();
    assert.equal(page.button.disabled, true);
    page.callback('turnstileSuccess');
    assert.equal(page.button.disabled, false);
    assert.equal(page.form.dataset.externallyBlocked, '0');
    assert.equal(page.events.at(-1).type, 'submit-block-change');
    assert.equal(page.events.at(-1).detail.blocked, false);
    page.submit();
    assert.equal(page.button.disabled, true);
    assert.equal(page.form.dataset.externallyBlocked, '1');
});

for (const attribute of ['expired', 'error', 'timeout']) {
    test(attribute + ' callback blocks submission after successful verification', () => {
        const callback = turnstileBlade.match(new RegExp('data-' + attribute + '-callback="([^"]+)"'))[1];
        const page = render();
        page.callback('turnstileSuccess');
        page.callback(callback);
        assert.equal(page.button.disabled, true);
        assert.equal(page.form.dataset.externallyBlocked, '1');
        assert.equal(page.events.at(-1).detail.blocked, true);
        assert.equal(page.warningVisible, attribute === 'error');
        page.callback('turnstileSuccess');
        assert.equal(page.button.disabled, false);
        assert.equal(page.warningVisible, false);
    });
}

for (const turnstileFirst of [true, false]) {
    test('Turnstile cooperates with cooldown in script order ' + turnstileFirst, () => {
        const page = render({ cooldown: true, remaining: 60, turnstileFirst });
        page.callback('turnstileSuccess');
        assert.equal(page.button.disabled, true);
        assert.equal(page.button.textContent, 'Try again in 01:00');
        page.advance(30);
        page.callback('turnstileBlocked');
        page.advance(30);
        assert.equal(page.button.disabled, true);
        assert.equal(page.button.textContent, 'Login');
        page.callback('turnstileSuccess');
        assert.equal(page.button.disabled, false);
        page.submit();
        assert.equal(page.button.disabled, true);
        page.advance(1);
        assert.equal(page.button.disabled, true);
    });
}

test('stalled script shows a warning after ten seconds and successful loading cancels it', () => {
    const stalled = render();
    stalled.advance(9);
    assert.equal(stalled.warningVisible, false);
    stalled.advance(1);
    assert.equal(stalled.warningVisible, true);
    assert.equal(stalled.button.disabled, true);
    stalled.callback('turnstileScriptReady');
    assert.equal(stalled.warningVisible, false);
    assert.equal(stalled.button.disabled, true);

    const loaded = render();
    loaded.callback('turnstileScriptReady');
    loaded.advance(10);
    assert.equal(loaded.warningVisible, false);
    assert.equal(loaded.button.disabled, true);
    loaded.callback('turnstileSuccess');
    assert.equal(loaded.button.disabled, false);
});

test('script load error shows warning immediately and Retry reloads the page', () => {
    const page = render();
    const callback = turnstileBlade.match(/onerror="([a-zA-Z]+)\(\)"/)[1];
    page.callback(callback);
    assert.equal(page.warningVisible, true);
    assert.equal(page.button.disabled, true);
    assert.equal(page.reloads, 0);
    page.retry();
    assert.equal(page.reloads, 1);
});

for (const [viewportWidth, containerWidth, size, theme] of [
    [320, 254, 'compact', 'light'],
    [390, 324, 'flexible', 'light'],
    [640, 254, 'flexible', 'light'],
    [1280, 400, 'flexible', 'light'],
]) {
    test(`Turnstile chooses ${size}/${theme} at ${viewportWidth}px`, () => {
        const page = render({ viewportWidth, containerWidth });
        assert.equal(page.renders.length, 0);
        page.callback('turnstileScriptReady');
        assert.deepEqual(page.renders, [{ size, theme }]);
        page.resize(viewportWidth, containerWidth);
        assert.equal(page.renders.length, 1);
    });
}

test('resizing replaces verification only when widget configuration changes', () => {
    const page = render({ viewportWidth: 320, containerWidth: 254 });
    page.resize(360, 294);
    assert.equal(page.renders.length, 0);
    page.callback('turnstileScriptReady');
    page.callback('turnstileSuccess');
    page.resize(375, 309);
    assert.deepEqual(page.removals, [1]);
    assert.deepEqual(page.renders.at(-1), { size: 'flexible', theme: 'light' });
    assert.equal(page.button.disabled, true);
    page.callback('turnstileSuccess');
    page.resize(430, 364);
    assert.equal(page.renders.length, 2);
    assert.equal(page.button.disabled, false);
    page.resize(768, 400);
    assert.deepEqual(page.removals, [1]);
    assert.equal(page.renders.length, 2);
    assert.deepEqual(page.renders.at(-1), { size: 'flexible', theme: 'light' });
    assert.equal(page.button.disabled, false);
});

test('replacement verification preserves an active login cooldown', () => {
    const page = render({ cooldown: true, remaining: 60, viewportWidth: 320, containerWidth: 254 });
    page.callback('turnstileScriptReady');
    page.callback('turnstileSuccess');
    page.resize(390, 324);
    page.callback('turnstileSuccess');
    assert.equal(page.button.disabled, true);
    page.advance(60);
    assert.equal(page.button.disabled, false);
});

test('a compact or not-yet-laid-out widget cannot force compact mode in a wide form', () => {
    for (const widgetWidth of [0, 150]) {
        const page = render({ viewportWidth: 390, containerWidth: 324, widgetWidth });
        page.callback('turnstileScriptReady');
        assert.deepEqual(page.renders, [{ size: 'flexible', theme: 'light' }]);
    }
});
