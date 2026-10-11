/**
 * Pure admin-app helpers for Mikesoft TeamVault.
 *
 * This file must stay dependency-free (no PDM object, no fetch, no DOM) so the
 * functions are unit-testable with `node --test tests/pdm-helpers.test.mjs`.
 * Impure values (REST base URL, current location) are passed in by callers;
 * when omitted they fall back to the localized runtime configuration.
 */
(function (window) {
    'use strict';

    const PDMHelpers = {
        escapeHtml(text) {
            const characters = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };

            return String(text ?? '').replace(/[&<>"']/g, character => characters[character]);
        },

        buildApiUrl(endpoint, restUrl) {
            const configuredBase = restUrl === undefined && typeof window.mstvConfig === 'object'
                ? window.mstvConfig.restUrl
                : restUrl;

            const base = String(configuredBase || '').replace(/\/+$/, '');
            const path = String(endpoint || '').replace(/^\/+/, '');

            if (base.includes('?')) {
                const queryIndex = path.indexOf('?');

                if (queryIndex === -1) {
                    return `${base}/${path}`;
                }

                return `${base}/${path.substring(0, queryIndex)}&${path.substring(queryIndex + 1)}`;
            }

            return `${base}/${path}`;
        },

        normalizeSameOriginUrl(value, href) {
            const baseHref = href === undefined && typeof window.location === 'object'
                ? window.location.href
                : href;

            try {
                const url = new URL(String(value || ''), baseHref);
                const baseOrigin = new URL(baseHref).origin;

                if (!['http:', 'https:'].includes(url.protocol) || url.origin !== baseOrigin) {
                    return '';
                }

                return url.toString();
            } catch (error) {
                return '';
            }
        }
    };

    window.PDMHelpers = PDMHelpers;
})(typeof window !== 'undefined' ? window : globalThis);
