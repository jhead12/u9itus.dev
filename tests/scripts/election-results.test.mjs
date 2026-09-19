import test from 'node:test';
import assert from 'node:assert/strict';
import { parseElectionStatus, generalElectionDate } from '../../scripts/lib/election-results.js';
import { spawnSync } from 'node:child_process';

test('primary advancement preserves candidacy and distinguishes general wins', () => {
  for (const text of ['Advanced General', 'Won Primary', 'Advanced to the general election']) {
    assert.equal(parseElectionStatus(text).result_status, 'advanced_to_general');
    assert.equal(parseElectionStatus(text).is_running_candidate, true);
  }
  assert.deepEqual(parseElectionStatus('Won General'), { result_status: 'won', election_stage: 'general', is_running_candidate: false });
  assert.equal(parseElectionStatus('Won').election_stage, null);
  assert.equal(parseElectionStatus('Lost Primary').result_status, 'lost');
});

test('general dates are computed per cycle instead of hard-coded November 3', () => {
  assert.equal(generalElectionDate(2026), '2026-11-03');
  assert.equal(generalElectionDate(2028), '2028-11-07');
  assert.equal(generalElectionDate(2030), '2030-11-05');
  assert.equal(generalElectionDate(2027), null);
});

test('state loop handles every state and national scope', () => {
  const result = spawnSync('bash', ['scripts/for-each-state.sh', 'ca, TX,ny', 'printf', '%s\n'], { encoding: 'utf8' });
  assert.equal(result.status, 0);
  assert.equal(result.stdout, '--state=CA\n--state=TX\n--state=NY\n');
  const national = spawnSync('bash', ['scripts/for-each-state.sh', '', 'printf', 'national'], { encoding: 'utf8' });
  assert.equal(national.status, 0);
  assert.equal(national.stdout, 'national');
});

test('state validation happens before any command runs', () => {
  const result = spawnSync('bash', ['scripts/for-each-state.sh', 'CA,INVALID', 'printf', 'should not run'], { encoding: 'utf8' });
  assert.equal(result.status, 2);
  assert.equal(result.stdout, '');
});

test('a failed state does not prevent auditing remaining states', () => {
  const result = spawnSync('bash', ['scripts/for-each-state.sh', 'CA,TX', 'bash', '-c', 'printf "%s\n" "$1"; [[ "$1" != --state=CA ]]', '_'], { encoding: 'utf8' });
  assert.equal(result.status, 1);
  assert.equal(result.stdout, '--state=CA\n--state=TX\n');
});

// Exercise the same shell entry point the map-refresh workflow invokes,
// substituting local executables so no network or database is touched.
import { mkdtempSync, writeFileSync, chmodSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
test('multi-state results use separate files and respect creation and dry-run switches', () => {
  const dir = mkdtempSync(join(tmpdir(), 'collection-test-'));
  try {
    for (const executable of ['node', 'php']) {
      const file = join(dir, executable);
      writeFileSync(file, '#!/usr/bin/env bash\nprintf "%s\\n" "$*" >> "$COLLECTION_LOG"\n');
      chmodSync(file, 0o755);
    }
    const log = join(dir, 'calls');
    const env = { ...process.env, PATH: `${dir}:${process.env.PATH}`, COLLECTION_LOG: log, ELECTION_YEAR: '2028', CREATE_MISSING: 'false', DRY_RUN: 'true' };
    const result = spawnSync('bash', ['scripts/for-each-state.sh', 'CA,TX,NY', 'bash', 'scripts/collect-election-results.sh'], { env, encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
    const lines = readFileSync(log, 'utf8').trim().split('\n');
    assert.equal(lines.length, 6);
    for (const [index, state] of ['CA', 'TX', 'NY'].entries()) {
      assert.match(lines[index * 2], new RegExp(`--state=${state} .*ballotpedia-results-2028-${state}\\.json`));
      assert.match(lines[index * 2 + 1], new RegExp(`--file=storage/app/imports/ballotpedia-results-2028-${state}\\.json --dry-run`));
      assert.doesNotMatch(lines[index * 2 + 1], /--create-missing/);
    }
    const enabled = spawnSync('bash', ['scripts/collect-election-results.sh'], { env: { ...env, CREATE_MISSING: 'true', DRY_RUN: 'false' }, encoding: 'utf8' });
    assert.equal(enabled.status, 0, enabled.stderr);
    assert.match(readFileSync(log, 'utf8').trim().split('\n').at(-1), /ballotpedia-results-2028\.json --create-missing$/);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});
