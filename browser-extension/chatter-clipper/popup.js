import { publicSourceUrl } from './source-url.js';

const status = document.querySelector('#status');
const urlField = document.querySelector('#source-url');
const headline = document.querySelector('#headline');
const excerpt = document.querySelector('#excerpt');
const button = document.querySelector('#continue');

function message(text, error = false) {
    status.textContent = text;
    status.classList.toggle('error', error);
}

async function capture() {
    try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        if (!tab || !publicSourceUrl(tab.url)) throw new Error('Open a public article or social post to clip it. Browser pages, local sites, inboxes, and message pages cannot be clipped.');
        urlField.value = tab.url;
        headline.value = (tab.title || '').slice(0, 240);
        document.querySelector('#clip-fields').disabled = false;
        try {
            const [capture] = await chrome.scripting.executeScript({
                target: { tabId: tab.id },
                func: () => {
                    const focused = document.activeElement;
                    // Never capture selected text from inputs, password fields or editors.
                    if (focused?.matches('input, textarea') || focused?.isContentEditable) return '';
                    return (window.getSelection()?.toString() || '').slice(0, 2000);
                },
            });
            excerpt.value = typeof capture?.result === 'string' ? capture.result : '';
            message(excerpt.value ? 'Selected text included. Review it before continuing.' : 'Link and title ready. An excerpt is optional.');
        } catch {
            message('This page does not allow text capture. You can still review its link and title.');
        }
    } catch (error) {
        message(error.message || 'Could not read this page. Try a public article or post.', true);
    }
}

document.querySelector('#clip-form').addEventListener('submit', async event => {
    event.preventDefault();
    if (!publicSourceUrl(urlField.value)) {
        message('Use a public HTTP or HTTPS link without login credentials, access tokens, or private message paths.', true);
        return;
    }
    if (!headline.value.trim()) {
        message('Add a suggested headline before continuing.', true);
        return;
    }
    const clip = { version: 1, source_url: urlField.value.trim(), headline: headline.value.trim(), source_excerpt: excerpt.value };
    const destination = 'https://www.u9itus.com/contribute/chatter/clip#clip=' + encodeURIComponent(JSON.stringify(clip));
    button.disabled = true;
    try {
        await chrome.tabs.create({ url: destination });
        window.close();
    } catch {
        button.disabled = false;
        message('Could not open U9itus. Please try again.', true);
    }
});

capture();
