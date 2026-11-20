import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const widgetSource = readFileSync(new URL('../public/tagasiside/widget.js', import.meta.url), 'utf8');

class FakeElement {
    append() {}
    setAttribute() {}
    addEventListener() {}
    remove() {}
    focus() {}
}

function loadWidget({ pageKey, href = 'https://limis.example/services', storedPages, storageAvailable = true } = {}) {
    const values = new Map();
    if (storedPages !== undefined) values.set('lf_pages', storedPages);

    const localStorage = {
        getItem(key) {
            if (!storageAvailable) throw new Error('storage disabled');
            return values.has(key) ? values.get(key) : null;
        },
        setItem(key, value) {
            if (!storageAvailable) throw new Error('storage disabled');
            values.set(key, value);
        }
    };

    const document = {
        currentScript: {
            dataset: pageKey === undefined ? {} : { pageKey },
            src: 'https://limis.example/tagasiside/widget.js'
        },
        body: new FakeElement(),
        hidden: false,
        createElement: () => new FakeElement(),
        querySelector: () => null
    };
    const window = {};

    runInNewContext(widgetSource, {
        document,
        window,
        localStorage,
        location: new URL(href),
        URL,
        addEventListener() {},
        setInterval() {}
    });

    return {
        api: window.LimisFeedback,
        pages: () => JSON.parse(values.get('lf_pages') || '[]')
    };
}

test('data-page-key takes precedence over query and path', () => {
    const widget = loadWidget({
        pageKey: 'service-search',
        href: 'https://limis.example/services?p=home'
    });

    assert.deepEqual(widget.pages(), ['service-search']);
});

test('query parameter is used when data-page-key is absent', () => {
    const widget = loadWidget({ href: 'https://limis.example/services?p=account' });

    assert.deepEqual(widget.pages(), ['account']);
});

test('path is used when page key and query parameter are absent', () => {
    const widget = loadWidget({ href: 'https://limis.example/services/search' });

    assert.deepEqual(widget.pages(), ['/services/search']);
});

test('setPage trims keys, ignores empty keys and does not duplicate pages', () => {
    const widget = loadWidget({ pageKey: 'home' });

    widget.api.setPage('  service-search  ');
    widget.api.setPage('service-search');
    widget.api.setPage('   ');

    assert.deepEqual(widget.pages(), ['home', 'service-search']);
});

test('invalid saved page data is recovered', () => {
    const widget = loadWidget({ pageKey: 'home', storedPages: '{invalid' });

    assert.deepEqual(widget.pages(), ['home']);
});

test('widget initialization and page updates tolerate unavailable local storage', () => {
    const widget = loadWidget({ pageKey: 'home', storageAvailable: false });

    assert.doesNotThrow(() => widget.api.setPage('service-search'));
});
