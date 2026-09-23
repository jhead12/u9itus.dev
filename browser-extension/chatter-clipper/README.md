# U9itus Source Clipper 0.2.0

An unpacked Chrome/Edge desktop pilot for approved U9itus contributors ("Web Reporters"). Extract the ZIP, open chrome://extensions or edge://extensions, enable Developer mode, and choose Load unpacked. Select the folder containing manifest.json and pin the extension. No API keys or extension login are needed. The U9itus website handoff routes must be deployed first.

On a public article or specific social post, optionally select a short excerpt, then either click the toolbar icon, or right-click the page (or the selection) and choose **Clip to U9itus** — both open the same review screen. Review/edit the link, title and excerpt, confirm the source is public, then Continue on U9itus. Sign in on the website if necessary, choose a politician, add relevance and submit for editorial review. All existing access checks and publication controls apply.

## Privacy

Permissions are activeTab, scripting, and contextMenus. The extension reads the invoked tab's URL/title and explicitly selected document text. It skips editable fields. It does not request host permissions, history, cookies or storage; no content scripts run and no data is read from any page on load. Capture is always user initiated, by one of the two clicks above. Nothing is sent until Continue is clicked. Remove any private or sensitive content before continuing; automated screening cannot prove that a page is public.

There is one event-driven background service worker (`background.js`). It does nothing until the "Clip to U9itus" menu item is clicked — it does not run on a schedule, does not persist, and does not read page content itself; it only passes along the url/title/selection Chrome already hands it as part of that same click.

The draft is sent to https://www.u9itus.com/contribute/chatter/clip in a URL fragment. Fragments are not part of the HTTP request. The first-party handoff immediately removes it from the address bar, stores it in tab-scoped sessionStorage for up to 30 minutes, and opens the authenticated form. Browser software may temporarily see the fragment; do not include secrets. Data is cleared on import, and no draft is submitted automatically. Submitted excerpts and contributor notes remain private editorial input. The extension does not capture images, video, hidden page content, full articles, or passwords.

There is no browser-store listing yet. To uninstall, use Remove on the browser's extensions page. On mobile or managed browsers that block unpacked extensions, use the U9itus submission form directly.

## Development

Source: browser-extension/chatter-clipper. Run `node scripts/package-chatter-extension.mjs` from the repository root after edits. This creates the distributable ZIP and updates the shared first-party URL validator. Server-side authorization, CSRF and validation remain authoritative; the extension is an untrusted client. Do not add automatic publishing or bypass contributor approvals.

References: https://developer.chrome.com/docs/extensions/develop/concepts/activeTab and https://developer.chrome.com/docs/extensions/reference/api/scripting and https://developer.chrome.com/docs/extensions/get-started/tutorial/hello-world
