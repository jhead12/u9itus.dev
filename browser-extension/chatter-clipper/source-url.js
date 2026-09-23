// This helper is also copied into the first-party importer by the packaging script.
export function publicSourceUrl(value) {
    if (typeof value !== 'string' || value.length > 1000) return false;
    try {
        const url = new URL(value);
        const host = url.hostname.toLowerCase().replace(/\.$/, '');
        if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return false;
        // Domain names only: no literal IPs, local names or local-network suffixes.
        if (!host.includes('.') || /^[\d.]+$/.test(host) || host.includes(':') || /\.(local|localhost|internal|test)$/.test(host)) return false;
        if (['mail.google.com', 'outlook.live.com', 'outlook.office.com', 'web.whatsapp.com', 'messenger.com'].some(site => host === site || host.endsWith('.' + site))) return false;
        if (/^\/(messages?|direct|inbox|chats?)(\/|$)/i.test(url.pathname)) return false;
        if ([...url.searchParams.keys()].some(key => /^(access_token|token|auth|authorization|password|secret|code|signature|x-amz-signature)$/i.test(key))) return false;
        return true;
    } catch {
        return false;
    }
}
