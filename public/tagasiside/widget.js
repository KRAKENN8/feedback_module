/**
 * Встраиваемый виджет обратной связи жителей для системы LIMIS.
 *
 * Возможности:
 * - Автономная работа без внешних зависимостей (чистый JS/CSS).
 * - Определение языка: URL (?lang=ru|et) -> data-lang -> localStorage('language') -> <html> lang -> 'et'.
 * - Синхронизация языка с ключом 'language' в localStorage LIMIS.
 * - Умное автоприглашение: показ после 45 секунд и 2 уникальных страниц (настраиваемо).
 * - Подавление показа на 30 дней после закрытия/отправки и блокировка при критических операциях.
 * - Доступность WCAG/EN 301 549: aria-modal, ловушка фокуса Tab, inert для фона, закрытие по Escape.
 * - Публичный JavaScript API: window.LimisFeedback (setCritical, setPage, setLanguage, getLanguage).
 */
(() => {
    const S = document.currentScript;
    const D = S ? S.dataset : {};
    const base = S && S.src ? S.src.replace(/widget\.js.*$/, '') : '';

    const ls = {
        get: k => {
            try {
                return localStorage.getItem(k);
            } catch (e) {
                return null;
            }
        },
        set: (k, v) => {
            try {
                localStorage.setItem(k, v);
            } catch (e) {}
        }
    };

    const resolveLang = () => {
        // 1. URL search param ?lang=ru or ?lang=et (matches LIMIS: https://limis.narva.ee/start/main.php?lang=ru)
        try {
            const urlLang = new URL(location.href).searchParams.get('lang');
            const cleanUrlLang = urlLang ? urlLang.trim().toLowerCase() : '';
            if (cleanUrlLang === 'ru' || cleanUrlLang === 'et') return cleanUrlLang;
        } catch (e) {}

        // 2. Explicit data-lang on script tag if specified ('ru' or 'et')
        const dataLang = (D && D.lang ? D.lang : '').trim().toLowerCase();
        if (dataLang === 'ru' || dataLang === 'et') return dataLang;

        // 3. LIMIS localStorage field 'language' ('ru' or 'et')
        const storedLang = (ls.get('language') || '').trim().toLowerCase();
        if (storedLang === 'ru' || storedLang === 'et') return storedLang;

        // 4. HTML document language (<html lang="ru">)
        const docLang = ((document.documentElement && document.documentElement.lang) || '').trim().toLowerCase();
        if (docLang.startsWith('ru')) return 'ru';

        return 'et';
    };

    let currentLang = resolveLang();
    // Synchronize to LIMIS localStorage 'language' key
    ls.set('language', currentLang);

    let ru = currentLang === 'ru';
    const t = a => a[ru ? 1 : 0];
    const intSetting = (value, fallback, min) => {
        if (typeof value !== 'string' || value.trim() === '') return fallback;
        const n = Number(value);
        return Number.isSafeInteger(n) && n >= min ? n : fallback;
    };
    const inviteAfterMs = intSetting(D.inviteAfterSeconds, 45, 0) * 1000;
    const inviteMinPages = intSetting(D.inviteMinPages, 2, 1);

    const Q = [
        {
            k: 'purpose',
            r: 1,
            q: ['Mis eesmärgil külastasite LIMIS-t?', 'С какой целью вы посетили LIMIS?'],
            o: [
                ['eservices', 'E-taotlused', 'Электронные заявления'],
                ['procurement', 'Hanked ja enampakkumised', 'Информация об аукционах и закупках'],
                ['property', 'Linnavara', 'Муниципальное имущество'],
                ['account', 'Isiklik konto', 'Личный кабинет'],
                ['appointment', 'Vastuvõtule registreerimine', 'Запись на приём'],
                ['other', 'Muu', 'Другое']
            ]
        },
        {
            k: 'completed',
            r: 1,
            q: ['Kas leidsite vajaliku teabe või saite soovitud toimingu tehtud?', 'Удалось ли вам найти нужную информацию или выполнить необходимое действие?'],
            o: [
                ['yes', 'Jah', 'Да'],
                ['partial', 'Osaliselt', 'Частично'],
                ['no', 'Ei', 'Нет'],
                ['none', 'Konkreetne eesmärk puudus', 'Не было конкретной цели']
            ]
        },
        {
            k: 'ease_rating',
            r: 1,
            n: 1,
            q: ['Kui lihtne oli LIMIS-t kasutada?', 'Насколько удобно было пользоваться LIMIS?'],
            o: [
                [1, 'Väga keeruline', 'Очень неудобно'],
                [2, 'Keeruline', 'Неудобно'],
                [3, 'Rahuldav', 'Удовлетворительно'],
                [4, 'Lihtne', 'Удобно'],
                [5, 'Väga lihtne', 'Очень удобно']
            ]
        },
        {
            k: 'difficulty',
            q: ['Mis valmistas kõige rohkem raskusi?', 'Что вызвало наибольшие затруднения?'],
            o: [
                ['search', 'Vajaliku teabe otsimine', 'Поиск нужной информации'],
                ['application', 'Taotluse täitmine', 'Заполнение заявления'],
                ['ui', 'Arusaamatu liides', 'Непонятный интерфейс'],
                ['mobile', 'Sait mobiilseadmes', 'Работа сайта на мобильном устройстве'],
                ['speed', 'Saidi kiirus', 'Скорость работы сайта'],
                ['none', 'Raskusi ei olnud', 'Ничего не вызвало затруднений'],
                ['other', 'Muu', 'Другое']
            ]
        }
    ];

    const X = {
        btn: ['Hinda LIMIS-t', 'Оценить LIMIS'],
        h: ['Aita muuta LIMIS paremaks!', 'Помогите сделать LIMIS лучше!'],
        sub: ['Osalemine on vabatahtlik, vastamine võtab umbes minuti.', 'Участие добровольное, заполнение занимает около минуты.'],
        c: ['Mida võiksime parandada?', 'Что нам следует улучшить?'],
        count: ['{count} / {max} tähemärki', '{count} / {max} символов'],
        w: ['Ärge märkige nimesid, isikukoode, kontaktandmeid ega konkreetsete asjade andmeid.', 'Не указывайте имена, личные коды, контактные данные или сведения о конкретных делах.'],
        send: ['Saada tagasiside', 'Отправить отзыв'],
        no: ['Mitte praegu', 'Не сейчас'],
        ok: ['Täname tagasiside eest!', 'Спасибо за ваш отзыв!'],
        req: ['Palun vastake kohustuslikele küsimustele.', 'Пожалуйста, ответьте на обязательные вопросы.'],
        er: ['Saatmine ebaõnnestus, proovige hiljem.', 'Не удалось отправить, попробуйте позже.'],
        rate: ['Liiga palju saatmisi, proovige hiljem.', 'Слишком много отправок, попробуйте позже.'],
        dup: ['Vastus on juba saadetud või seanss aegunud, proovige uuesti.', 'Отзыв уже отправлен или сессия устарела, попробуйте ещё раз.'],
        priv: ['Andmekaitsetingimused', 'Условия защиты данных']
    };

    // Ключи локального хранилища (localStorage)
    const K = 'lf_until'; // Временная метка подавления автоприглашений (на 30 дней)
    const P = 'lf_pages'; // Набор посещённых уникальных страниц
    const M = 'lf_ms';    // Накопленное активное время на страницах в миллисекундах

    // Подавление автоприглашения на 30 дней после отправки или закрытия
    const sup = () => ls.set(K, String(Date.now() + 30 * 864e5));
    const suppressed = () => +ls.get(K) > Date.now();

    /**
     * Вспомогательный конструктор DOM-элементов.
     */
    const el = (tag, a = {}, kids = []) => {
        const e = document.createElement(tag);
        Object.assign(e, a);
        kids.forEach(k => e.append(k));
        return e;
    };

    let crit = D.critical === '1';
    let open = false;
    let inv = null;
    let last = null;
    let inertNodes = [];

    /**
     * Переводит фоновые элементы страницы в состояние inert при открытом модальном окне,
     * предотвращая взаимодействие и чтение фонового контента ассистивными технологиями.
     */
    const setBackgroundInert = (active, overlay) => {
        try {
            if (active) {
                inertNodes = [];
                if (document.body && document.body.children) {
                    for (const child of document.body.children) {
                        if (child !== overlay && child.tagName !== 'SCRIPT' && !child.inert) {
                            child.inert = true;
                            inertNodes.push(child);
                        }
                    }
                }
            } else {
                for (const node of inertNodes) {
                    node.inert = false;
                }
                inertNodes = [];
            }
        } catch (e) {}
    };

    const btn = el('button', {
        className: 'lf-btn',
        type: 'button',
        textContent: t(X.btn)
    });
    btn.onclick = () => show();
    document.body.append(btn);

    function hideInv() {
        if (inv) {
            inv.remove();
        }
        inv = null;
    }

    function close() {
        setBackgroundInert(false);
        document.querySelector('.lf-ov')?.remove();
        open = false;
        sup();
        const target = (last && document.body && document.body.contains(last)) ? last : btn;
        target?.focus();
    }

    function show() {
        if (open) return;
        open = true;
        hideInv();
        last = document.activeElement;

        const ov = el('div', { className: 'lf-ov' });
        const m = el('form', { className: 'lf-m', noValidate: true });
        m.setAttribute('role', 'dialog');
        m.setAttribute('aria-modal', 'true');
        m.setAttribute('aria-labelledby', 'lf-h');

        const h = el('h2', { id: 'lf-h', textContent: t(X.h) });
        m.append(h, el('p', { textContent: t(X.sub) }));

        Q.forEach(q => {
            const f = el('fieldset');
            const lg = el('legend', { textContent: t(q.q) + (q.r ? ' *' : '') });
            f.append(lg);

            q.o.forEach(o => {
                const i = el('input', {
                    type: 'radio',
                    name: q.k,
                    value: o[0]
                });
                f.append(el('label', {}, [
                    i,
                    ' ' + (q.n ? o[0] + ' – ' : '') + (ru ? o[2] : o[1])
                ]));
            });
            m.append(f);
        });

        const cf = el('fieldset');
        const ta = el('textarea', { id: 'lf-c', maxLength: 500 });
        const cl = el('label', {
            htmlFor: 'lf-c',
            textContent: t(X.c),
            className: 'lf-comment-label'
        });
        const wr = el('p', {
            textContent: t(X.w),
            className: 'lf-warning'
        });
        wr.id = 'lf-w';
        const counter = el('p', { id: 'lf-count', className: 'lf-count' });
        const updateCounter = () => {
            counter.textContent = t(X.count)
                .replace('{count}', String([...ta.value].length))
                .replace('{max}', String(ta.maxLength));
        };
        updateCounter();
        ta.setAttribute('aria-describedby', 'lf-w lf-count');
        ta.addEventListener('input', updateCounter);
        cf.append(cl, wr, ta, counter);
        m.append(cf);

        const hp = el('input', {
            type: 'text',
            name: 'website',
            tabIndex: -1,
            autocomplete: 'off',
            className: 'lf-hp'
        });
        hp.setAttribute('aria-hidden', 'true');
        m.append(hp);

        const er = el('p', { className: 'lf-err' });
        er.setAttribute('role', 'alert');
        er.setAttribute('aria-live', 'polite');
        m.append(er);

        const sb = el('button', {
            type: 'submit',
            textContent: t(X.send)
        });
        const nb = el('button', {
            type: 'button',
            textContent: t(X.no)
        });
        nb.onclick = () => close();
        const priv = el('p', { className: 'lf-priv' });
        const privLink = el('a', {
            href: 'https://www.narva.ee/andmekaitsetingimused',
            target: '_blank',
            rel: 'noopener noreferrer',
            textContent: t(X.priv)
        });
        priv.append(privLink);
        m.append(el('div', { className: 'lf-a' }, [sb, nb]), priv);

        let token = '';
        fetch(base + 'token.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(j => token = j.token)
            .catch(() => {});

        m.onsubmit = async e => {
            e.preventDefault();
            if (sb.disabled) return;
            const v = k => (m.querySelector(`input[name=${k}]:checked`) || {}).value;

            if (!v('purpose') || !v('completed') || !v('ease_rating')) {
                er.textContent = t(X.req);
                return;
            }

            sb.disabled = true;
            er.textContent = '';

            const body = {
                token,
                lang: currentLang,
                purpose: v('purpose'),
                completed: v('completed'),
                ease_rating: +v('ease_rating'),
                difficulty: v('difficulty') || null,
                comment: ta.value.slice(0, 500) || null,
                page_group: D.page || null,
                website: hp.value
            };

            try {
                const r = await fetch(base + 'submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });

                if (r.ok) {
                    sup();
                    const thanks = el('h2', { id: 'lf-h', textContent: t(X.ok) });
                    const ok = el('button', {
                        type: 'button',
                        className: 'lf-primary',
                        textContent: 'OK',
                        onclick: () => close()
                    });
                    m.replaceChildren(
                        thanks,
                        el('div', { className: 'lf-a lf-success-actions' }, [ok])
                    );
                    ok.focus();
                } else {
                    if (r.status === 429) {
                        er.textContent = t(X.rate);
                    } else if (r.status === 409) {
                        er.textContent = t(X.dup);
                        fetch(base + 'token.php', { cache: 'no-store' })
                            .then(r => r.json())
                            .then(j => token = j.token)
                            .catch(() => {});
                        sb.disabled = false;
                    } else {
                        er.textContent = t(X.er);
                        fetch(base + 'token.php', { cache: 'no-store' })
                            .then(r => r.json())
                            .then(j => token = j.token)
                            .catch(() => {});
                        sb.disabled = false;
                    }
                }
            } catch (x) {
                er.textContent = t(X.er);
                sb.disabled = false;
            }
        };

        m.onkeydown = e => {
            if (e.key === 'Escape') close();
            if (e.key === 'Tab') {
                const f = [...m.querySelectorAll('input:not([tabindex="-1"]),textarea,button:not(:disabled)')];
                const a = f[0];
                const z = f[f.length - 1];

                if (e.shiftKey && document.activeElement === a) {
                    e.preventDefault();
                    z.focus();
                } else if (!e.shiftKey && document.activeElement === z) {
                    e.preventDefault();
                    a.focus();
                }
            }
        };

        ov.append(m);
        document.body.append(ov);
        setBackgroundInert(true, ov);
        m.querySelector('input').focus();
    }

    let pages;
    try {
        const storedPages = JSON.parse(ls.get(P) || '[]');
        pages = new Set(Array.isArray(storedPages) ? storedPages.filter(page => typeof page === 'string') : []);
    } catch (e) {
        pages = new Set();
    }

    const addPage = key => {
        if (typeof key !== 'string' || !key.trim()) return;
        pages.add(key.trim().slice(0, 200));
        ls.set(P, JSON.stringify([...pages]));
    };

    const page = D.pageKey || new URL(location.href).searchParams.get('p') || location.pathname;
    addPage(page);

    // Автоприглашение: две разные страницы и 45 секунд активности, если оно не подавлено и нет важной операции.
    setInterval(() => {
        if (document.hidden) return;
        const ms = (+ls.get(M) || 0) + 1000;
        ls.set(M, String(ms));

        if (!inv && !open && !crit && !suppressed() && pages.size >= inviteMinPages && ms >= inviteAfterMs) {
            inv = el('div', { className: 'lf-inv' });
            inv.setAttribute('role', 'region');
            inv.setAttribute('aria-label', t(X.h));

            const o = el('button', {
                type: 'button',
                textContent: t(X.btn),
                onclick: () => show()
            });
            const d = el('button', {
                type: 'button',
                textContent: t(X.no),
                onclick: () => {
                    hideInv();
                    sup();
                },
                className: 'lf-inv-dismiss'
            });

            inv.append(
                el('p', {
                    textContent: t(X.h),
                    className: 'lf-inv-title'
                }),
                o,
                d
            );
            document.body.append(inv);
        }
    }, 1000);

    // API интеграции: событие window 'limis-feedback:critical' {detail:{active:bool}} или LimisFeedback.setCritical(bool)
    const set = b => {
        crit = !!b;
        if (crit) {
            hideInv();
        }
    };
    const setLang = l => {
        const normalized = String(l || '').trim().toLowerCase();
        if (normalized === 'ru' || normalized === 'et') {
            currentLang = normalized;
            ru = currentLang === 'ru';
            ls.set('language', currentLang);
            if (btn) {
                btn.textContent = t(X.btn);
            }
        }
    };
    window.LimisFeedback = {
        setCritical: set,
        setPage: addPage,
        setLanguage: setLang,
        getLanguage: () => currentLang
    };
    addEventListener('limis-feedback:critical', e => set(e.detail && e.detail.active));
    addEventListener('limis-feedback:lang', e => setLang(e.detail && (e.detail.lang || e.detail.language)));
    addEventListener('limis-feedback:language', e => setLang(e.detail && (e.detail.lang || e.detail.language)));
})();
