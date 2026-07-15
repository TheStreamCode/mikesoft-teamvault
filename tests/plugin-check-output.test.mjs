import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

import { parsePluginCheckOutput } from '../tools/parse-plugin-check-output.mjs';

const parserPath = fileURLToPath(
  new URL('../tools/parse-plugin-check-output.mjs', import.meta.url),
);

function runParser(raw, checkExit = '0') {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'teamvault-plugin-check-'));
  const resultsPath = path.join(directory, 'results.txt');

  try {
    fs.writeFileSync(resultsPath, raw);
    const args = [parserPath, resultsPath];
    if (checkExit !== null) {
      args.push(checkExit);
    }
    return spawnSync(process.execPath, args, {
      encoding: 'utf8',
    });
  } finally {
    fs.rmSync(directory, { recursive: true, force: true });
  }
}

test('parses file-prefixed Plugin Check warning blocks', () => {
  const result = parsePluginCheckOutput(`FILE: COPYRIGHT.md
[{"line":0,"column":0,"type":"WARNING","code":"unexpected_markdown_file","message":"Unexpected file","docs":""}]
FILE: plugin.php
[{"line":12,"column":4,"type":"ERROR","code":"unsafe_call","message":"Unsafe call","docs":""}]
`);

  assert.equal(result.length, 2);
  assert.equal(result[0].file, 'COPYRIGHT.md');
  assert.equal(result[0].type, 'WARNING');
  assert.equal(result[1].file, 'plugin.php');
  assert.equal(result[1].type, 'ERROR');
});

test('rejects JSON findings without FILE prefixes', () => {
  assert.throws(
    () =>
      parsePluginCheckOutput(
        '[{"line":1,"column":2,"type":"WARNING","code":"example","message":"Example","docs":""}]',
      ),
    /must use FILE-prefixed JSON blocks/,
  );
});

test('accepts the documented Plugin Check success message', () => {
  assert.deepEqual(
    parsePluginCheckOutput('Success: Checks complete. No errors found.'),
    [],
  );
});

test('rejects success-like text that can conceal errors', () => {
  assert.throws(
    () => parsePluginCheckOutput('Success: 2 errors found. No errors found in cache.'),
    /must use FILE-prefixed JSON blocks/,
  );
});

test('rejects multiline success-like text', () => {
  assert.throws(
    () => parsePluginCheckOutput('Success:\nChecks complete. No errors found.'),
    /must use FILE-prefixed JSON blocks/,
  );
});

test('rejects success messages with different casing or spacing', () => {
  assert.throws(
    () => parsePluginCheckOutput('success: Checks complete. No errors found.'),
    /must use FILE-prefixed JSON blocks/,
  );
  assert.throws(
    () => parsePluginCheckOutput('Success:  Checks complete. No errors found.'),
    /must use FILE-prefixed JSON blocks/,
  );
});

test('rejects empty output', () => {
  assert.throws(() => parsePluginCheckOutput('  \n'), /produced no output/);
});

test('rejects unrelated valid JSON', () => {
  assert.throws(
    () => parsePluginCheckOutput('{"status":"ok"}'),
    /must use FILE-prefixed JSON blocks/,
  );
});

test('rejects empty JSON finding arrays', () => {
  assert.throws(() => parsePluginCheckOutput('[]'), /must use FILE-prefixed JSON blocks/);
  assert.throws(
    () => parsePluginCheckOutput('FILE: plugin.php\n[]'),
    /contains no findings/,
  );
});

test('rejects findings without a supported severity', () => {
  assert.throws(
    () =>
      parsePluginCheckOutput(
        'FILE: plugin.php\n[{"line":1,"column":2,"code":"example","message":"missing severity","docs":""}]',
      ),
    /must have type ERROR or WARNING/,
  );
});

test('rejects non-string finding severities', () => {
  assert.throws(
    () =>
      parsePluginCheckOutput(
        'FILE: plugin.php\n[{"line":1,"column":2,"type":["WARNING"],"code":"example","message":"Example","docs":""}]',
      ),
    /must have type ERROR or WARNING/,
  );
});

test('rejects incomplete finding objects', () => {
  assert.throws(
    () => parsePluginCheckOutput('FILE: plugin.php\n[{"type":"WARNING"}]'),
    /must include line, column, code, message, and docs/,
  );
});

test('rejects empty file-prefixed blocks', () => {
  assert.throws(
    () => parsePluginCheckOutput('FILE: plugin.php\n'),
    /has no JSON findings/,
  );
});

test('rejects malformed non-JSON output', () => {
  assert.throws(
    () => parsePluginCheckOutput('Plugin Check crashed'),
    /must use FILE-prefixed JSON blocks/,
  );
});

test('workflow disables command parsing before target plugin activation', () => {
  const workflow = fs.readFileSync(
    fileURLToPath(new URL('../.github/workflows/ci.yml', import.meta.url)),
    'utf8',
  );
  const stopCommands = workflow.indexOf('echo "::stop-commands::$STOP_MARKER"');
  const pluginActivation = workflow.indexOf('wp plugin activate mikesoft-teamvault');
  const resumeCommands = workflow.indexOf('echo "::$STOP_MARKER::"');

  assert.ok(stopCommands >= 0, 'workflow must disable command parsing');
  assert.ok(stopCommands < pluginActivation, 'command parsing must stop before activation hooks run');
  assert.ok(resumeCommands > pluginActivation, 'command parsing must resume after plugin output');
});

test('CLI fails when Plugin Check exits zero without output', () => {
  const result = runParser('');

  assert.equal(result.status, 1);
  assert.match(result.stderr, /produced no output/);
});

test('CLI fails when the checker exit code is invalid', () => {
  const result = runParser('[]', 'invalid');

  assert.equal(result.status, 1);
  assert.match(result.stderr, /Invalid Plugin Check exit code/);
});

test('CLI requires the checker exit code', () => {
  const result = runParser('Success: Checks complete. No errors found.', null);

  assert.equal(result.status, 1);
  assert.match(result.stderr, /exit code is required/);
});

test('CLI propagates a nonzero checker exit', () => {
  const result = runParser(
    'FILE: plugin.php\n[{"line":1,"column":2,"type":"WARNING","code":"example","message":"Example","docs":""}]',
    '1',
  );

  assert.equal(result.status, 1);
  assert.match(result.stderr, /exited 1 without a parsed error/);
});

test('CLI accepts the documented success output with exit zero', () => {
  const result = runParser('Success: Checks complete. No errors found.');

  assert.equal(result.status, 0);
  assert.match(result.stdout, /Plugin Check passed/);
});

test('CLI keeps finding messages on one line', () => {
  const result = runParser(
    'FILE: plugin.php\n[{"line":1,"column":2,"type":"ERROR","code":"unsafe_call","message":"Unsafe call\\n::warning::injected","docs":""}]',
  );

  assert.equal(result.status, 1);
  assert.doesNotMatch(result.stdout, /(?:^|\r?\n)::warning::injected(?:\r?\n|$)/);
});

test('CLI neutralizes legacy workflow commands in finding messages', () => {
  const result = runParser(
    'FILE: plugin.php\n[{"line":1,"column":2,"type":"ERROR","code":"unsafe_call","message":"Unsafe ##[warning] injected","docs":""}]',
  );

  assert.equal(result.status, 1);
  assert.doesNotMatch(result.stdout, /##\[warning\]/);
});
