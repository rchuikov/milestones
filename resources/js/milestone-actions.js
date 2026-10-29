import { refreshMilestoneTimer } from './timer.js';

const errorPageUrl = document.querySelector('[data-error-page-url]')?.dataset.errorPageUrl;

function redirectToError(code) {
    const url = new URL(errorPageUrl, window.location.href);
    url.searchParams.set('code', code);

    window.location.assign(url.href);
}

async function refreshTodayAnniversaries() {
    const container = document.querySelector('#today-anniversaries');

    const response = await fetch(container.dataset.url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    });

    if (!response.ok) {
        redirectToError(response.status);
        return;
    }

    container.innerHTML = await response.text();
}

function setCardBusy(card, busy) {
    card.toggleAttribute('data-busy', busy);
    [...card.children].forEach(element => {
        element.inert = busy;
    });
    card.querySelectorAll('button').forEach(button => {
        button.disabled = busy;
    });
}

document.addEventListener('submit', async event => {
    const form = event.target;

    if (!form.matches('form[data-milestone-action]')) {
        return;
    }

    event.preventDefault();

    const card = form.closest('[data-milestone-id]');
    const button = form.querySelector('[type="submit"]');

    if (!card || !button || card.hasAttribute('data-busy') || button.disabled) {
        return;
    }

    let currentCard = card;
    setCardBusy(card, true);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: new FormData(form),
        });

        if (!response.ok) {
            redirectToError(response.status);
            return;
        }

        const html = await response.text();
        const template = document.createElement('template');

        template.innerHTML = html.trim();

        const newCard = template.content.firstElementChild;

        if (
            !newCard ||
            newCard.dataset.milestoneId !== card.dataset.milestoneId
        ) {
            redirectToError(500);
            return;
        }

        const previousTop = card.getBoundingClientRect().top;

        currentCard = newCard;
        setCardBusy(newCard, true);
        refreshMilestoneTimer(card, newCard);
        await refreshTodayAnniversaries();

        const delta = newCard.getBoundingClientRect().top - previousTop;

        if (delta !== 0) {
            window.scrollBy(0, delta);
        }
    } catch (error) {
        console.error(error);
        redirectToError(500);
    } finally {
        setCardBusy(card, false);
        if (currentCard !== card) {
            setCardBusy(currentCard, false);
        }
    }
});
