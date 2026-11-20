(() => {
    const S = document.currentScript;
    const D = S.dataset;
    const ru = D.lang === 'ru';
    const t = a => a[ru ? 1 : 0];
    const base = S.src.replace(/widget\.js.*$/, '');
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
        rate: ['Liiga palju saatmisi, proovige hiljem.', 'Слишком много отправок, попробуйте позже.']
    };

    const K = 'lf_until';
    const P = 'lf_pages';
    const M = 'lf_ms';

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

    const sup = () => ls.set(K, String(Date.now() + 30 * 864e5));
    const suppressed = () => +ls.get(K) > Date.now();

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
        document.querySelector('.lf-ov')?.remove();
        open = false;
        sup();
        (last || btn).focus();
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
            style: 'font-weight:600'
        });
        const wr = el('p', {
            textContent: t(X.w),
            style: 'font-size:14px;margin:.2rem 0'
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
        m.append(el('div', { className: 'lf-a' }, [sb, nb]));

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
                lang: D.lang === 'ru' ? 'ru' : 'et',
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
                    const ok = el('button', {
                        type: 'button',
                        className: 'lf-primary',
                        textContent: 'OK',
                        onclick: () => close()
                    });
                    m.replaceChildren(
                        h,
                        el('p', { textContent: t(X.ok) }),
                        el('div', { className: 'lf-a lf-success-actions' }, [ok])
                    );
                    ok.focus();
                } else {
                    er.textContent = t(r.status === 429 ? X.rate : X.er);
                    if (r.status !== 409) {
                        if (r.status !== 429) {
                            fetch(base + 'token.php', { cache: 'no-store' })
                                .then(r => r.json())
                                .then(j => token = j.token);
                        }
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
        m.querySelector('input').focus();
    }

    const pages = new Set(JSON.parse(ls.get(P) || '[]'));
    const page = new URL(location.href).searchParams.get('p') || D.page || location.pathname;
    pages.add(page);
    ls.set(P, JSON.stringify([...pages]));

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
                style: 'margin-left:.4rem'
            });

            inv.append(
                el('p', {
                    textContent: t(X.h),
                    style: 'margin:0 0 .5rem;font-weight:600'
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
    window.LimisFeedback = { setCritical: set };
    addEventListener('limis-feedback:critical', e => set(e.detail && e.detail.active));
})();
