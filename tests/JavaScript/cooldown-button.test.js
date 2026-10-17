import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const blade = await readFile(new URL('../../resources/views/components/cooldown-button.blade.php', import.meta.url), 'utf8');
const source = blade.match(/<script>([\s\S]*?)<\/script>/)[1];

function render({ remaining = '', restart = '0', now = 100000, storage = new Map(), storageAllowed = true, blocked = false, hasForm = true } = {}) {
    let clock = now;
    let tick;
    const listeners = {};
    const form = hasForm ? {
        dataset: { externallyBlocked: blocked ? '1' : '0' },
        addEventListener(name, callback) { listeners[name] = callback; },
    } : null;
    const button = {
        closest(selector) {
            assert.equal(selector, 'form');
            return form;
        },
        dataset: {
            label: 'Send email', countdownLabel: 'Send again in', storagePrefix: 'password_reset',
            seconds: '60', remainingSeconds: String(remaining), restart,
        },
        disabled: false,
        textContent: '',
    };
    vm.runInNewContext(source, {
        document: { querySelectorAll: () => [button] },
        Date: { now: () => clock },
        sessionStorage: {
            getItem(key) {
                assert.equal(storageAllowed, true, 'Server cooldown must not read sessionStorage');
                return storage.get(key) ?? null;
            },
            setItem(key, value) {
                assert.equal(storageAllowed, true, 'Server cooldown must not write sessionStorage');
                storage.set(key, value);
            },
            removeItem(key) {
                assert.equal(storageAllowed, true, 'Server cooldown must not remove sessionStorage');
                storage.delete(key);
            },
        },
        setInterval(callback, delay) {
            assert.equal(delay, 1000);
            tick = callback;
        },
    });
    return {
        button,
        block(blocked) {
            listeners['submit-block-change']({ detail: { blocked } });
        },
        advance(seconds) {
            clock += seconds * 1000;
            tick();
        },
    };
}

test('server countdown formats a minute and enables button at its deadline', () => {
    const page = render({ remaining: 60, storageAllowed: false });
    assert.equal(page.button.disabled, true);
    assert.equal(page.button.textContent, 'Send again in 01:00');
    page.advance(1);
    assert.equal(page.button.textContent, 'Send again in 00:59');
    page.advance(59);
    assert.equal(page.button.disabled, false);
    assert.equal(page.button.textContent, 'Send email');
});

test('reload uses remaining seconds from server and ignores stale browser storage', () => {
    const storage = new Map([['password_reset_available_at', '999999999']]);
    render({ remaining: 60, storage, storageAllowed: false });
    const reloaded = render({ remaining: 30, now: 130000, storage, storageAllowed: false });
    assert.equal(reloaded.button.textContent, 'Send again in 00:30');
    reloaded.advance(30);
    assert.equal(reloaded.button.disabled, false);
    assert.equal(storage.get('password_reset_available_at'), '999999999');
});

test('opening page after expiry does not start another countdown', () => {
    const page = render({ remaining: 0, storageAllowed: false });
    assert.equal(page.button.disabled, false);
    assert.equal(page.button.textContent, 'Send email');
    page.advance(10);
    assert.equal(page.button.disabled, false);
});

test('new successful server send supplies a fresh deadline', () => {
    const first = render({ remaining: 1, storageAllowed: false });
    first.advance(1);
    assert.equal(first.button.disabled, false);
    const resent = render({ remaining: 60, now: 101000, storageAllowed: false });
    assert.equal(resent.button.disabled, true);
    assert.equal(resent.button.textContent, 'Send again in 01:00');
});

test('password reset retains local cooldown across reload and clears it after expiry', () => {
    const storage = new Map();
    const page = render({ restart: '1', storage });
    assert.equal(page.button.textContent, 'Send again in 01:00');
    assert.equal(storage.get('password_reset_available_at'), '160000');
    const reloaded = render({ now: 130000, storage });
    assert.equal(reloaded.button.textContent, 'Send again in 00:30');
    reloaded.advance(30);
    assert.equal(reloaded.button.disabled, false);
    assert.equal(storage.has('password_reset_available_at'), false);
    assert.equal(render({ now: 170000, storage }).button.disabled, false);
});

test('external verification blocks an idle button and can be restored after success', () => {
    const page = render({ blocked: true });
    assert.equal(page.button.disabled, true);
    assert.equal(page.button.textContent, 'Send email');
    page.block(false);
    assert.equal(page.button.disabled, false);
    page.block(true);
    page.advance(10);
    assert.equal(page.button.disabled, true);
});

for (const options of [{ remaining: 60 }, { restart: '1' }]) {
    test('verification success cannot bypass cooldown ' + JSON.stringify(options), () => {
        const page = render({ ...options, blocked: true });
        page.block(false);
        assert.equal(page.button.disabled, true);
        assert.equal(page.button.textContent, 'Send again in 01:00');
        page.advance(60);
        assert.equal(page.button.disabled, false);
    });

    test('cooldown expiry preserves external blocking ' + JSON.stringify(options), () => {
        const page = render({ ...options, blocked: true });
        page.advance(60);
        assert.equal(page.button.disabled, true);
        assert.equal(page.button.textContent, 'Send email');
        page.block(false);
        assert.equal(page.button.disabled, false);
    });
}

test('button without a containing form still follows its cooldown', () => {
    const page = render({ remaining: 1, hasForm: false });
    assert.equal(page.button.disabled, true);
    page.advance(1);
    assert.equal(page.button.disabled, false);
});
