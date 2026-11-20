import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const widgetSource = readFileSync(new URL('../public/tagasiside/widget.js', import.meta.url), 'utf8');

class FakeElement {
    constructor(tagName = 'div', attrs = {}) {
        this.tagName = tagName.toUpperCase();
        this.className = attrs.className || '';
        this.type = attrs.type || '';
        this.name = attrs.name || '';
        this.id = attrs.id || '';
        this.textContent = attrs.textContent || '';
        this.value = attrs.value || '';
        this.disabled = !!attrs.disabled;
        this.tabIndex = attrs.tabIndex !== undefined ? attrs.tabIndex : 0;
        this.children = [];
        this.parentElement = null;
        this.ownerDocument = null;
        this.attributes = new Map();
        this.listeners = new Map();
        this.onclick = null;
        this.onkeydown = null;
        this.onsubmit = null;
    }

    append(...kids) {
        for (const k of kids) {
            if (typeof k === 'string') {
                this.textContent += k;
            } else if (k instanceof FakeElement) {
                k.parentElement = this;
                k.ownerDocument = this.ownerDocument;
                this.children.push(k);
            }
        }
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    getAttribute(name) {
        return this.attributes.get(name) || null;
    }

    addEventListener(event, fn) {
        if (!this.listeners.has(event)) this.listeners.set(event, []);
        this.listeners.get(event).push(fn);
    }

    remove() {
        if (this.parentElement) {
            const idx = this.parentElement.children.indexOf(this);
            if (idx !== -1) {
                this.parentElement.children.splice(idx, 1);
            }
            this.parentElement = null;
        }
    }

    focus() {
        if (this.ownerDocument) {
            this.ownerDocument.activeElement = this;
        }
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    }

    querySelectorAll(selector) {
        const matches = [];
        const testMatch = el => {
            if (selector === '.lf-ov' && el.className?.includes('lf-ov')) return true;
            if (selector === '.lf-inv' && el.className?.includes('lf-inv')) return true;
            if (selector === '.lf-btn' && el.className?.includes('lf-btn')) return true;
            if (selector === '.lf-inv-dismiss' && el.className?.includes('lf-inv-dismiss')) return true;
            if (selector === 'input' && el.tagName === 'INPUT') return true;
            if (selector === 'button' && el.tagName === 'BUTTON') return true;
            if (selector.includes('input:not([tabindex="-1"]),textarea,button:not(:disabled)')) {
                if (el.disabled) return false;
                if (el.tabIndex === -1 || el.getAttribute('tabindex') === '-1') return false;
                return el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'BUTTON';
            }
            return false;
        };

        const walk = el => {
            if (testMatch(el)) matches.push(el);
            for (const child of el.children) walk(child);
        };
        for (const child of this.children) walk(child);
        return matches;
    }
}

function loadWidget({
    pageKey,
    href = 'https://limis.example/services',
    storedPages,
    storageAvailable = true,
    hidden = false,
    critical = '0',
    storedMs,
    storedUntil,
    inviteAfterSeconds,
    inviteMinPages
} = {}) {
    const values = new Map();
    if (storedPages !== undefined) values.set('lf_pages', storedPages);
    if (storedMs !== undefined) values.set('lf_ms', String(storedMs));
    if (storedUntil !== undefined) values.set('lf_until', String(storedUntil));

    const localStorage = {
        getItem(key) {
            if (!storageAvailable) throw new Error('storage disabled');
            return values.has(key) ? values.get(key) : null;
        },
        setItem(key, value) {
            if (!storageAvailable) throw new Error('storage disabled');
            values.set(key, String(value));
        }
    };

    let intervalFn;
    const setInterval = fn => {
        intervalFn = fn;
        return 1;
    };

    const windowListeners = new Map();
    const addEventListener = (ev, fn) => {
        if (!windowListeners.has(ev)) windowListeners.set(ev, []);
        windowListeners.get(ev).push(fn);
    };

    const doc = {
        hidden,
        activeElement: null,
        currentScript: {
            dataset: {
                ...(pageKey === undefined ? {} : { pageKey }),
                critical,
                ...(inviteAfterSeconds === undefined ? {} : { inviteAfterSeconds: String(inviteAfterSeconds) }),
                ...(inviteMinPages === undefined ? {} : { inviteMinPages: String(inviteMinPages) })
            },
            src: 'https://limis.example/tagasiside/widget.js'
        },
        body: null,
        createElement: (tag, attrs) => {
            const el = new FakeElement(tag, attrs);
            el.ownerDocument = doc;
            return el;
        },
        querySelector: sel => doc.body?.querySelector(sel) || null,
        querySelectorAll: sel => doc.body?.querySelectorAll(sel) || []
    };
    doc.body = new FakeElement('body');
    doc.body.ownerDocument = doc;

    const window = {};

    runInNewContext(widgetSource, {
        document: doc,
        window,
        localStorage,
        location: new URL(href),
        URL,
        addEventListener,
        setInterval,
        fetch: async () => ({ json: async () => ({ token: 'mock-token' }) })
    });

    return {
        api: window.LimisFeedback,
        pages: () => JSON.parse(values.get('lf_pages') || '[]'),
        document: doc,
        storage: values,
        tick: () => intervalFn?.(),
        emit: (event, detail) => {
            for (const fn of windowListeners.get(event) || []) {
                fn({ detail });
            }
        }
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

test('auto-invite appears after 45 seconds and at least 2 distinct pages', () => {
    const widget = loadWidget({
        pageKey: 'page-1',
        storedPages: JSON.stringify(['page-1', 'page-2']),
        storedMs: 44000
    });

    assert.equal(widget.document.querySelector('.lf-inv'), null);
    widget.tick(); // advances to 45000 ms
    const invite = widget.document.querySelector('.lf-inv');
    assert.notEqual(invite, null);
    assert.equal(invite.getAttribute('role'), 'region');
});

test('auto-invite does not appear when fewer than 2 distinct pages have been visited', () => {
    const widget = loadWidget({
        pageKey: 'page-1',
        storedPages: JSON.stringify(['page-1']),
        storedMs: 50000
    });

    widget.tick();
    assert.equal(widget.document.querySelector('.lf-inv'), null);
});

test('auto-invite does not advance elapsed timer when document is hidden', () => {
    const widget = loadWidget({
        pageKey: 'page-1',
        storedPages: JSON.stringify(['page-1', 'page-2']),
        storedMs: 40000,
        hidden: true
    });

    widget.tick();
    assert.equal(widget.storage.get('lf_ms'), '40000');
    assert.equal(widget.document.querySelector('.lf-inv'), null);
});

test('auto-invite is suppressed when critical operation is active via dataset or API', () => {
    const widget = loadWidget({
        pageKey: 'page-1',
        storedPages: JSON.stringify(['page-1', 'page-2']),
        storedMs: 44000,
        critical: '1'
    });

    widget.tick();
    assert.equal(widget.document.querySelector('.lf-inv'), null);

    // Turn critical off via API
    widget.api.setCritical(false);
    widget.tick();
    assert.notEqual(widget.document.querySelector('.lf-inv'), null);

    // Turn critical on via event -> hides invite
    widget.emit('limis-feedback:critical', { active: true });
    assert.equal(widget.document.querySelector('.lf-inv'), null);
});

test('auto-invite is suppressed when 30-day suppression timestamp is active', () => {
    const futureTimestamp = Date.now() + 10 * 864e5; // 10 days in future
    const widget = loadWidget({
        pageKey: 'page-1',
        storedPages: JSON.stringify(['page-1', 'page-2']),
        storedMs: 50000,
        storedUntil: futureTimestamp
    });

    widget.tick();
    assert.equal(widget.document.querySelector('.lf-inv'), null);
});

test('dismissing auto-invite hides it and sets 30-day suppression timestamp', () => {
    const widget = loadWidget({
        pageKey: 'page-1',
        storedPages: JSON.stringify(['page-1', 'page-2']),
        storedMs: 44000
    });

    widget.tick();
    const invite = widget.document.querySelector('.lf-inv');
    assert.notEqual(invite, null);

    const dismissBtn = invite.querySelector('.lf-inv-dismiss');
    assert.notEqual(dismissBtn, null);
    dismissBtn.onclick();

    assert.equal(widget.document.querySelector('.lf-inv'), null);
    const suppressedUntil = Number(widget.storage.get('lf_until'));
    assert.ok(suppressedUntil > Date.now() + 29 * 864e5);
});

test('clicking trigger button opens modal dialog and sets accessibility attributes', () => {
    const widget = loadWidget({ pageKey: 'home' });
    const triggerBtn = widget.document.querySelector('.lf-btn');
    assert.notEqual(triggerBtn, null);

    triggerBtn.onclick();
    const overlay = widget.document.querySelector('.lf-ov');
    assert.notEqual(overlay, null);

    const dialog = overlay.children.find(el => el.getAttribute('role') === 'dialog');
    assert.notEqual(dialog, null);
    assert.equal(dialog.getAttribute('aria-modal'), 'true');
    assert.equal(dialog.getAttribute('aria-labelledby'), 'lf-h');
});

test('escape key closes modal and sets 30-day suppression', () => {
    const widget = loadWidget({ pageKey: 'home' });
    const triggerBtn = widget.document.querySelector('.lf-btn');
    triggerBtn.onclick();

    assert.notEqual(widget.document.querySelector('.lf-ov'), null);

    const dialog = widget.document.querySelector('.lf-ov').children[0];
    dialog.onkeydown({ key: 'Escape' });

    assert.equal(widget.document.querySelector('.lf-ov'), null);
    const suppressedUntil = Number(widget.storage.get('lf_until'));
    assert.ok(suppressedUntil > Date.now() + 29 * 864e5);
});

test('tab key navigation is trapped within modal dialog controls', () => {
    const widget = loadWidget({ pageKey: 'home' });
    const triggerBtn = widget.document.querySelector('.lf-btn');
    triggerBtn.onclick();

    const dialog = widget.document.querySelector('.lf-ov').children[0];
    const controls = dialog.querySelectorAll('input:not([tabindex="-1"]),textarea,button:not(:disabled)');
    const firstControl = controls[0];
    const lastControl = controls[controls.length - 1];

    let defaultPrevented = false;
    const preventDefault = () => { defaultPrevented = true; };

    // Shift+Tab on first control focuses last control
    widget.document.activeElement = firstControl;
    dialog.onkeydown({ key: 'Tab', shiftKey: true, preventDefault });
    assert.equal(defaultPrevented, true);
    assert.equal(widget.document.activeElement, lastControl);

    // Tab on last control focuses first control
    defaultPrevented = false;
    widget.document.activeElement = lastControl;
    dialog.onkeydown({ key: 'Tab', shiftKey: false, preventDefault });
    assert.equal(defaultPrevented, true);
    assert.equal(widget.document.activeElement, firstControl);
});
