import { publicSourceUrl } from './source-url.js';

const status = document.querySelector('#status');
const urlField = document.querySelector('#source-url');
const headline = document.querySelector('#headline');
const excerpt = document.querySelector('#excerpt');
const fields = document.querySelector('#clip-fields');
const button = document.querySelector('#continue');
const NOT_CLIPPABLE = 'Open a public article or social post to clip it. Browser pages, local sites, inboxes, and message pages cannot be clipped.';

function message(text, error = false) {
    status.textContent = text;
    status.classList.toggle('error', error);
}

function populate(url, title, excerptText, note) {
    if (!publicSourceUrl(url)) throw new Error(NOT_CLIPPABLE);
    urlField.value = url;
    headline.value = (title || '').slice(0, 240);
    excerpt.value = (excerptText || '').slice(0, 2000);
    fields.disabled = false;
    message(note);
}

async function captureFromActiveTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab) throw new Error(NOT_CLIPPABLE);
    populate(tab.url, tab.title, '', 'Link and title ready. An excerpt is optional.');
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
}

// Right-click "Clip to U9itus" hands off the originating page's url/title/selection
// here via background.js, since this page is no longer that tab (it's its own tab).
function captureFromMenu(hash) {
    const data = JSON.parse(decodeURIComponent(hash.slice('#from-menu='.length)));
    const note = data.excerpt
        ? 'Selected text included from the page. Review it before continuing.'
        : 'Link and title ready from the page. An excerpt is optional.';
    populate(data.url, data.title, data.excerpt, note);
}

async function capture() {
    try {
        const hash = window.location.hash;
        if (hash.startsWith('#from-menu=')) {
            document.documentElement.classList.add('standalone');
            captureFromMenu(hash);
            // Clear the payload from the URL now that it's been read into the form.
            window.history.replaceState(null, '', window.location.pathname);
        } else {
            await captureFromActiveTab();
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
