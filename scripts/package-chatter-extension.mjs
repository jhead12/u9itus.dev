import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, unlinkSync, rmdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const root = fileURLToPath(new URL('../', import.meta.url));
const source = resolve(root, 'browser-extension/chatter-clipper');
const manifest = JSON.parse(readFileSync(join(source, 'manifest.json'), 'utf8'));
const output = resolve(root, 'public/downloads');
mkdirSync(output, { recursive: true });
// The importer and extension must enforce identical URL screening.
copyFileSync(join(source, 'source-url.js'), resolve(root, 'public/js/chatter-source-url.js'));
const temporary = mkdtempSync(join(tmpdir(), 'u9itus-extension-'));
const archive = join(temporary, 'clipper.zip');
execFileSync('zip', ['-q', archive, 'manifest.json', 'popup.html', 'popup.css', 'popup.js', 'source-url.js', 'README.md'], { cwd: source });
const target = join(output, `u9itus-source-clipper-${manifest.version}.zip`);
copyFileSync(archive, target);
unlinkSync(archive);
rmdirSync(temporary);
console.log(`Packaged ${target}`);
