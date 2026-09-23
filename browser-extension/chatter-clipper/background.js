// Registers one right-click menu item and reacts only to its click — no polling,
// no page access on load, and it adds no new host/content reach: the tab/selection
// data it hands off is exactly what a context-menu click already carries as the
// user's own gesture, the same trust boundary activeTab already relies on.
const MENU_ID = 'u9itus-clip';

chrome.runtime.onInstalled.addListener(() => {
    chrome.contextMenus.create({
        id: MENU_ID,
        title: 'Clip to U9itus',
        contexts: ['page', 'selection'],
    });
});

chrome.contextMenus.onClicked.addListener((info, tab) => {
    if (info.menuItemId !== MENU_ID || !tab) return;

    const payload = {
        url: tab.url || '',
        title: (tab.title || '').slice(0, 240),
        excerpt: (info.selectionText || '').slice(0, 2000),
    };
    const hash = '#from-menu=' + encodeURIComponent(JSON.stringify(payload));
    chrome.tabs.create({ url: chrome.runtime.getURL('popup.html') + hash });
});
