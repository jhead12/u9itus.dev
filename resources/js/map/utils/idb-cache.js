/**
 * Lightweight IndexedDB cache for large map assets (TopoJSON, TIGERweb GeoJSON).
 *
 * Why IndexedDB instead of localStorage?
 *  - localStorage is limited to ~5 MB and blocks the main thread on large writes.
 *  - IndexedDB is async, quota is 50–100+ MB, and works offline via Service Worker.
 *
 * API:
 *   idbGet(key)          → Promise<value | undefined>
 *   idbSet(key, value, ttlMs)  → Promise<void>
 *   idbDel(key)          → Promise<void>
 */

const DB_NAME    = 'u9_map_cache';
const DB_VERSION = 1;
const STORE      = 'assets';

let _db = null;

function openDb() {
    if (_db) return Promise.resolve(_db);
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = e => {
            /** @type {IDBDatabase} */
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'key' });
            }
        };
        req.onsuccess = e => { _db = e.target.result; resolve(_db); };
        req.onerror   = e => reject(e.target.error);
    });
}

/**
 * Read a cached entry. Returns undefined on miss or expiry.
 * @param {string} key
 * @returns {Promise<any>}
 */
export async function idbGet(key) {
    try {
        const db    = await openDb();
        const tx    = db.transaction(STORE, 'readonly');
        const store = tx.objectStore(STORE);
        const entry = await new Promise((res, rej) => {
            const req = store.get(key);
            req.onsuccess = e => res(e.target.result);
            req.onerror   = e => rej(e.target.error);
        });
        if (!entry) return undefined;
        if (entry.expiresAt && Date.now() > entry.expiresAt) {
            // Stale — delete asynchronously, return undefined.
            idbDel(key).catch(() => {});
            return undefined;
        }
        return entry.value;
    } catch {
        return undefined;
    }
}

/**
 * Write a value to the cache.
 * @param {string} key
 * @param {any}    value   Must be structured-cloneable (plain objects, arrays, etc.)
 * @param {number} [ttlMs=0]  0 = never expires
 * @returns {Promise<void>}
 */
export async function idbSet(key, value, ttlMs = 0) {
    try {
        const db    = await openDb();
        const tx    = db.transaction(STORE, 'readwrite');
        const store = tx.objectStore(STORE);
        const entry = {
            key,
            value,
            expiresAt: ttlMs > 0 ? Date.now() + ttlMs : null,
        };
        await new Promise((res, rej) => {
            const req = store.put(entry);
            req.onsuccess = () => res();
            req.onerror   = e => rej(e.target.error);
        });
    } catch { /* non-fatal */ }
}

/**
 * Remove a cached entry.
 * @param {string} key
 * @returns {Promise<void>}
 */
export async function idbDel(key) {
    try {
        const db    = await openDb();
        const tx    = db.transaction(STORE, 'readwrite');
        const store = tx.objectStore(STORE);
        await new Promise((res, rej) => {
            const req = store.delete(key);
            req.onsuccess = () => res();
            req.onerror   = e => rej(e.target.error);
        });
    } catch { /* non-fatal */ }
}
