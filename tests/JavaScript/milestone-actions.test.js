import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

const source = await readFile(new URL('../../resources/js/milestone-actions.js', import.meta.url), 'utf8');

function cardHtml(active = true, id = '1') {
    const actions = active ? ['reset', 'stop'] : ['start'];
    return `<div data-milestone-id="${id}">
        ${actions.map(action => `<form method="POST" action="/milestones/${id}/${action}" data-milestone-action>
            <input type="hidden" name="_token" value="csrf-token">
            <button type="submit">${action}</button>
        </form>`).join('')}
    </div>`;
}

function response(body, status = 200) {
    return { ok: status >= 200 && status < 300, status, text: async () => body };
}

async function page(t, fetch) {
    const dom = new JSDOM(`<!doctype html><html><body>
        <div data-error-page-url="/error" hidden></div>
        <div id="today-anniversaries" data-url="/milestones/anniversaries">Old anniversaries</div>
        ${cardHtml()}
        <form action="/logout" method="POST"><button type="submit">Logout</button></form>
    </body></html>`, { url: 'https://milestones.test/' });
    t.after(() => dom.window.close());

    const document = dom.window.document;
    const redirects = [];
    const replacements = [];
    const scrolls = [];
    let submit;
    document.addEventListener = (name, handler) => {
        assert.equal(name, 'submit');
        submit = handler;
    };
    const context = vm.createContext({
        document,
        window: {
            location: { href: dom.window.location.href, assign: url => redirects.push(url) },
            scrollBy: (...args) => scrolls.push(args),
        },
        URL,
        FormData: dom.window.FormData,
        fetch,
        console: { error() {} },
    });
    const timers = new vm.SyntheticModule(['refreshMilestoneTimer'], function () {
        this.setExport('refreshMilestoneTimer', (oldCard, newCard) => {
            replacements.push([oldCard, newCard]);
            oldCard.replaceWith(newCard);
        });
    }, { context });
    const module = new vm.SourceTextModule(source, { context });
    await module.link(specifier => {
        assert.equal(specifier, './timer.js');
        return timers;
    });
    await module.evaluate();

    return {
        document, redirects, replacements, scrolls,
        get card() { return document.querySelector('[data-milestone-id="1"]'); },
        submit(form) {
            const event = new dom.window.Event('submit', { bubbles: true, cancelable: true });
            Object.defineProperty(event, 'target', { value: form });
            return { event, done: submit(event) };
        },
    };
}

test('reset, stop and start submit CSRF data and replace the card and anniversaries without navigation', async t => {
    const requests = [];
    const p = await page(t, async (url, options) => {
        requests.push({ url, options });
        if (options.method === 'POST') {
            return response(cardHtml(!url.endsWith('/stop')));
        }
        return response(url === '/milestones/anniversaries' ? 'Updated anniversaries' : 'Unexpected URL');
    });

    for (const action of ['reset', 'stop', 'start']) {
        const oldCard = p.card;
        const form = oldCard.querySelector(`form[action$="/${action}"]`);
        const { event, done } = p.submit(form);
        assert.equal(event.defaultPrevented, true);
        assert.equal(form.querySelector('button').disabled, true);
        await done;

        assert.notEqual(p.card, oldCard);
        assert.equal(oldCard.isConnected, false);
        assert.equal(p.document.querySelector('#today-anniversaries').textContent, 'Updated anniversaries');
        assert.equal(p.card.querySelector('button').disabled, false);
    }

    assert.equal(p.replacements.length, 3);
    assert.equal(requests.length, 6);
    for (const { url, options } of requests) {
        assert.equal(options.headers['X-Requested-With'], 'XMLHttpRequest');
        if (options.method === 'POST') {
            assert.match(url, /^https:\/\/milestones.test\/milestones\/1\/(reset|stop|start)$/);
            assert.equal(options.body.get('_token'), 'csrf-token');
        } else {
            assert.equal(url, '/milestones/anniversaries');
        }
    }
    assert.deepEqual(p.redirects, []);
    assert.deepEqual(p.scrolls, []);
});

test('the whole card stays locked through POST, replacement and anniversary refresh, then unlocks', async t => {
    let resolvePost;
    let resolveAnniversaries;
    let refreshStarted;
    const refreshing = new Promise(resolve => { refreshStarted = resolve; });
    let requests = 0;
    const p = await page(t, async (url, options) => {
        requests++;
        if (options.method === 'POST') {
            return new Promise(resolve => { resolvePost = resolve; });
        }
        refreshStarted();
        return new Promise(resolve => { resolveAnniversaries = resolve; });
    });
    const unrelated = p.submit(p.document.querySelector('form[action="/logout"]'));
    await unrelated.done;
    assert.equal(unrelated.event.defaultPrevented, false);
    assert.equal(requests, 0);

    const oldCard = p.card;
    const form = oldCard.querySelector('form[action$="/reset"]');
    const pending = p.submit(form);
    assert.equal(oldCard.hasAttribute('data-busy'), true);
    assert.ok([...oldCard.querySelectorAll('button')].every(button => button.disabled));
    await p.submit(form).done;
    await p.submit(oldCard.querySelector('form[action$="/stop"]')).done;
    assert.equal(requests, 1);

    resolvePost(response(cardHtml(false)));
    await refreshing;
    assert.notEqual(p.card, oldCard);
    assert.equal(p.card.hasAttribute('data-busy'), true);
    assert.ok([...p.card.querySelectorAll('button')].every(button => button.disabled));
    await p.submit(p.card.querySelector('form[action$="/start"]')).done;
    assert.equal(requests, 2);

    resolveAnniversaries(response(''));
    await pending.done;
    assert.equal(requests, 2);
    assert.equal(oldCard.hasAttribute('data-busy'), false);
    assert.equal(p.card.hasAttribute('data-busy'), false);
    assert.ok([...p.card.querySelectorAll('button')].every(button => !button.disabled));
});

for (const status of [401, 403, 404, 419, 500]) {
    test(`POST failure ${status} opens the error page without replacing the card`, async t => {
        let requests = 0;
        const p = await page(t, async () => {
            requests++;
            return response('', status);
        });
        const card = p.card;
        const form = card.querySelector('form');
        await p.submit(form).done;
        assert.deepEqual(p.redirects, [`https://milestones.test/error?code=${status}`]);
        assert.equal(p.card, card);
        assert.equal(p.replacements.length, 0);
        assert.equal(form.querySelector('button').disabled, false);
        assert.equal(card.hasAttribute('data-busy'), false);
        assert.ok([...card.querySelectorAll('button')].every(button => !button.disabled));
        assert.equal(requests, 1);
    });
}

for (const html of ['', '<html><body>Login page</body></html>', cardHtml(true, '2')]) {
    test(`invalid card response is rejected: ${html.slice(0, 45)}`, async t => {
        const p = await page(t, async () => response(html));
        const card = p.card;
        await p.submit(card.querySelector('form')).done;
        assert.equal(p.card, card);
        assert.equal(p.replacements.length, 0);
        assert.deepEqual(p.redirects, ['https://milestones.test/error?code=500']);
    });
}

test('a network failure opens the error page and releases the button', async t => {
    const p = await page(t, async () => { throw new TypeError('Network unavailable'); });
    const form = p.card.querySelector('form');
    await p.submit(form).done;
    assert.equal(form.querySelector('button').disabled, false);
    assert.equal(p.card.hasAttribute('data-busy'), false);
    assert.deepEqual(p.redirects, ['https://milestones.test/error?code=500']);
});

test('anniversary refresh failure opens the error page after a successful card update', async t => {
    const p = await page(t, async (url, options) => options.method === 'POST'
        ? response(cardHtml(false))
        : response('', 419));
    await p.submit(p.card.querySelector('form[action$="/stop"]')).done;
    assert.equal(p.replacements.length, 1);
    assert.ok(p.card.querySelector('form[action$="/start"]'));
    assert.equal(p.card.hasAttribute('data-busy'), false);
    assert.equal(p.card.querySelector('button').disabled, false);
    assert.equal(p.document.querySelector('#today-anniversaries').textContent, 'Old anniversaries');
    assert.deepEqual(p.redirects, ['https://milestones.test/error?code=419']);
});
