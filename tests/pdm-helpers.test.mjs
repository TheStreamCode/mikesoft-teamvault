/**
 * Behavioral tests for the pure admin-app helpers.
 *
 * Run with: node --test tests/pdm-helpers.test.mjs
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

const helpersSource = fs.readFileSync(
    path.join(import.meta.dirname, '..', 'assets', 'js', 'admin-app-helpers.js'),
    'utf8'
);

function loadHelpers({ mstvConfig = null, href = 'http://example.test/wp-admin/admin.php' } = {}) {
    const sandboxWindow = {
        mstvConfig,
        location: new URL(href)
    };

    const sandbox = {
        window: sandboxWindow,
        URL
    };
    sandbox.globalThis = sandbox;

    vm.runInNewContext(helpersSource, sandbox, { filename: 'admin-app-helpers.js' });

    return sandboxWindow.PDMHelpers;
}

test('escapeHtml encodes all five HTML-significant characters including both quote types', () => {
    const helpers = loadHelpers();

    assert.equal(helpers.escapeHtml('<a href="x" data-y=\'z\'>A & B</a>'),
        '&lt;a href=&quot;x&quot; data-y=&#039;z&#039;&gt;A &amp; B&lt;/a&gt;');
    assert.equal(helpers.escapeHtml(null), '');
    assert.equal(helpers.escapeHtml(undefined), '');
    assert.equal(helpers.escapeHtml(42), '42');
});

test('escapeHtml leaves plain text untouched', () => {
    const helpers = loadHelpers();

    assert.equal(helpers.escapeHtml('Quarterly report (final).pdf'), 'Quarterly report (final).pdf');
});

test('buildApiUrl joins pretty-permalink REST bases', () => {
    const helpers = loadHelpers();

    assert.equal(
        helpers.buildApiUrl('files/upload', 'http://example.test/wp-json/mstv/v1/'),
        'http://example.test/wp-json/mstv/v1/files/upload'
    );
});

test('buildApiUrl appends query-style REST route arguments with &', () => {
    const helpers = loadHelpers();

    const base = 'http://example.test/?rest_route=/mstv/v1';

    assert.equal(
        helpers.buildApiUrl('folders', base),
        'http://example.test/?rest_route=/mstv/v1/folders'
    );
    assert.equal(
        helpers.buildApiUrl('browser?page=2', base),
        'http://example.test/?rest_route=/mstv/v1/browser&page=2'
    );
});

test('buildApiUrl normalizes duplicated slashes in base and endpoint', () => {
    const helpers = loadHelpers();

    assert.equal(
        helpers.buildApiUrl('/files/upload', 'http://example.test/wp-json/mstv/v1///'),
        'http://example.test/wp-json/mstv/v1/files/upload'
    );
});

test('buildApiUrl falls back to the localized runtime configuration', () => {
    const helpers = loadHelpers({ mstvConfig: { restUrl: 'http://example.test/wp-json/mstv/v1/' } });

    assert.equal(
        helpers.buildApiUrl('settings'),
        'http://example.test/wp-json/mstv/v1/settings'
    );
});

test('normalizeSameOriginUrl accepts same-origin http and https absolute URLs', () => {
    const helpers = loadHelpers({ href: 'http://example.test/wp-admin/admin.php' });

    assert.equal(
        helpers.normalizeSameOriginUrl('http://example.test/wp-admin/admin-post.php?action=x'),
        'http://example.test/wp-admin/admin-post.php?action=x'
    );
});

test('normalizeSameOriginUrl resolves relative URLs against the admin location', () => {
    const helpers = loadHelpers({ href: 'http://example.test/wp-admin/admin.php' });

    assert.equal(
        helpers.normalizeSameOriginUrl('/wp-admin/admin-post.php?action=x'),
        'http://example.test/wp-admin/admin-post.php?action=x'
    );
});

test('normalizeSameOriginUrl rejects cross-origin and dangerous schemes', () => {
    const helpers = loadHelpers({ href: 'http://example.test/wp-admin/admin.php' });

    assert.equal(helpers.normalizeSameOriginUrl('https://evil.example/steal'), '');
    assert.equal(helpers.normalizeSameOriginUrl('javascript:alert(1)'), '');
    assert.equal(helpers.normalizeSameOriginUrl('data:text/html,hi'), '');
});

test('normalizeSameOriginUrl resolves empty values to the same-origin base location (legacy behavior)', () => {
    const helpers = loadHelpers({ href: 'http://example.test/wp-admin/admin.php' });

    // new URL('', href) resolves to the base itself; same-origin, so the
    // normalizer accepts it exactly like the previous inline implementation.
    assert.equal(helpers.normalizeSameOriginUrl(''), 'http://example.test/wp-admin/admin.php');
    assert.equal(helpers.normalizeSameOriginUrl(null), 'http://example.test/wp-admin/admin.php');
});

test('normalizeSameOriginUrl falls back to the current window location', () => {
    const helpers = loadHelpers({ href: 'http://example.test/wp-admin/admin.php' });

    assert.equal(
        helpers.normalizeSameOriginUrl('http://example.test/wp-json/mstv/v1/settings'),
        'http://example.test/wp-json/mstv/v1/settings'
    );
});
