/*
 * Student exam client.
 *
 * The exam is a single page: the server sends the questions once (POST /start),
 * all answers live in `state` in the browser (and in a localStorage draft so a
 * refresh does not lose work), and everything is sent back in ONE multipart
 * POST at the end. No polling, no websockets.
 *
 * ---------------------------------------------------------------------
 * Anti-cheating hook point (NOT implemented yet)
 * ---------------------------------------------------------------------
 * window.MathubExamGuard is a tiny registry. A guard module is an object
 *     { name: 'no-paste', init: function (ctx) { ... } }
 * registered with MathubExamGuard.register(module) before the exam starts
 * (e.g. from a separate script loaded after this one). On exam start every
 * module's init(ctx) is called with:
 *     ctx.root      the element containing all questions
 *     ctx.exam      the exam (id, title, questions, settings)
 *     ctx.settings  exam.settings, reserved per-exam anti-cheat options
 *     ctx.log       function (type, detail) -> records an event
 * Every question is a  <section data-question-id data-question-type>  and every
 * answer control has class "exam-input", so a module can listen on ctx.root
 * with event delegation (paste/copy/cut, focus, ...). Document-level things like
 * visibilitychange / window blur can be attached to document/window directly.
 * Logged events are sent with the submission, stored in the submission JSON
 * ("events") and listed on the teacher's submission page.
 */
(function () {
    'use strict';

    var app = document.getElementById('exam-app');
    if (!app) { return; }

    var T = JSON.parse(document.getElementById('exam-i18n').textContent);
    function t(key) { return T['exam.' + key] || key; }

    var MAX_FILE = 10 * 1024 * 1024;

    // ------------------------------------------------------------------
    // Guard registry (see header comment)
    // ------------------------------------------------------------------
    var Guard = window.MathubExamGuard = {
        events: [],
        modules: [],
        register: function (mod) { Guard.modules.push(mod); },
        log: function (type, detail) {
            Guard.events.push({ type: String(type), at: Math.floor(Date.now() / 1000), detail: detail === undefined ? null : detail });
        },
        init: function (ctx) {
            ctx.log = Guard.log;
            Guard.modules.forEach(function (m) {
                try { m.init(ctx); } catch (e) { /* a broken guard must never break the exam */ }
            });
        }
    };

    // ------------------------------------------------------------------
    // State
    // ------------------------------------------------------------------
    var state = {
        sid: null,
        exam: null,
        answers: {},   // qid -> string | string[] | {leftId: rightId}
        files: {},     // qid -> { blob, name }
        drawData: {},  // qid -> SignaturePad point groups (to re-open a drawing)
        submitting: false,
        done: false
    };

    var startForm = document.getElementById('exam-start');
    var startError = document.getElementById('exam-start-error');
    var main = document.getElementById('exam-main');
    var storageKey = app.dataset.storageKey;

    // ------------------------------------------------------------------
    // Draft (localStorage). Files and drawings are not persisted.
    // ------------------------------------------------------------------
    function loadDraft() {
        try { return JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (e) { return null; }
    }
    var saveTimer = null;
    function saveDraft() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            try { localStorage.setItem(storageKey, JSON.stringify({ sid: state.sid, answers: state.answers })); } catch (e) { /* storage full/blocked: fine */ }
        }, 300);
    }
    function clearDraft() {
        clearTimeout(saveTimer);
        try { localStorage.removeItem(storageKey); } catch (e) { /* ignore */ }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'class') { node.className = attrs[k]; }
            else if (k === 'text') { node.textContent = attrs[k]; }
            else { node.setAttribute(k, attrs[k]); }
        });
        (children || []).forEach(function (c) { if (c) { node.appendChild(c); } });
        return node;
    }

    function shuffle(arr) {
        var a = arr.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = a[i]; a[i] = a[j]; a[j] = tmp;
        }
        return a;
    }

    function setAnswer(qid, value) {
        state.answers[qid] = value;
        saveDraft();
    }

    function isAnswered(q) {
        if (q.type === 'upload' || q.type === 'draw') { return !!state.files[q.id]; }
        var a = state.answers[q.id];
        if (q.type === 'checkbox') { return Array.isArray(a) && a.length > 0; }
        if (q.type === 'match') {
            return !!a && q.lefts.every(function (l) { return a[l.id]; });
        }
        return typeof a === 'string' && a.trim() !== '';
    }

    function fmtPoints(p) {
        var n = Math.round(p * 100) / 100;
        return n + ' ' + t('points');
    }

    // ------------------------------------------------------------------
    // Question renderers: each returns the answer-area element
    // ------------------------------------------------------------------
    var renderers = {};

    renderers.radio = function (q) {
        var wrap = el('div', { class: 'q-options' });
        q.options.forEach(function (o) {
            var input = el('input', { type: 'radio', name: 'q_' + q.id, value: o.id, class: 'exam-input' });
            input.checked = state.answers[q.id] === o.id;
            input.addEventListener('change', function () { setAnswer(q.id, o.id); });
            wrap.appendChild(el('label', { class: 'q-option' }, [input, el('span', { text: o.text })]));
        });
        return wrap;
    };

    renderers.checkbox = function (q) {
        var wrap = el('div', { class: 'q-options' });
        function collect() {
            var ids = [];
            wrap.querySelectorAll('input:checked').forEach(function (i) { ids.push(i.value); });
            setAnswer(q.id, ids);
        }
        var current = state.answers[q.id] || [];
        q.options.forEach(function (o) {
            var input = el('input', { type: 'checkbox', value: o.id, class: 'exam-input' });
            input.checked = current.indexOf(o.id) !== -1;
            input.addEventListener('change', collect);
            wrap.appendChild(el('label', { class: 'q-option' }, [input, el('span', { text: o.text })]));
        });
        return wrap;
    };

    renderers.select = function (q) {
        var select = el('select', { class: 'exam-input' }, [el('option', { value: '', text: t('select_placeholder') })]);
        q.options.forEach(function (o) { select.appendChild(el('option', { value: o.id, text: o.text })); });
        select.value = state.answers[q.id] || '';
        select.addEventListener('change', function () { setAnswer(q.id, select.value); });
        return select;
    };

    renderers.short_text = function (q) {
        var input = el('input', { type: 'text', class: 'exam-input', maxlength: '1000', autocomplete: 'off' });
        input.value = state.answers[q.id] || '';
        input.addEventListener('input', function () { setAnswer(q.id, input.value); });
        return input;
    };

    renderers.number = function (q) {
        var input = el('input', { type: 'text', inputmode: 'decimal', class: 'exam-input', maxlength: '50', autocomplete: 'off', placeholder: t('number_placeholder') });
        input.value = state.answers[q.id] || '';
        input.addEventListener('input', function () { setAnswer(q.id, input.value); });
        return input;
    };

    renderers.long_text = function (q) {
        var area = el('textarea', { class: 'exam-input', rows: '8', maxlength: '20000' });
        area.value = state.answers[q.id] || '';
        area.addEventListener('input', function () { setAnswer(q.id, area.value); });
        return area;
    };

    // Connect A to B: both columns are shuffled once per student. The right
    // column is lettered; each left item has a dropdown to pick its letter.
    renderers.match = function (q) {
        var lefts = shuffle(q.lefts);
        var rights = shuffle(q.rights);
        var letters = {};
        var rightList = el('ol', { class: 'match-rights', type: 'A' });
        rights.forEach(function (r, i) {
            letters[r.id] = String.fromCharCode(65 + i);
            rightList.appendChild(el('li', { text: r.text }));
        });
        var current = state.answers[q.id] || {};
        var rows = el('div', { class: 'match-lefts' });
        lefts.forEach(function (l) {
            var select = el('select', { class: 'exam-input' }, [el('option', { value: '', text: '—' })]);
            rights.forEach(function (r) { select.appendChild(el('option', { value: r.id, text: letters[r.id] })); });
            select.value = current[l.id] || '';
            select.addEventListener('change', function () {
                var a = state.answers[q.id] || {};
                if (select.value) { a[l.id] = select.value; } else { delete a[l.id]; }
                setAnswer(q.id, a);
            });
            rows.appendChild(el('div', { class: 'match-row' }, [el('span', { class: 'match-left', text: l.text }), select]));
        });
        return el('div', { class: 'match' }, [
            el('p', { class: 'empty', text: t('match_hint') }),
            el('div', { class: 'match-cols' }, [rows, rightList])
        ]);
    };

    renderers.upload = function (q) {
        var info = el('span', { class: 'file-info' });
        var input = el('input', { type: 'file', class: 'exam-input' });
        var remove = el('button', { type: 'button', class: 'button-secondary', text: t('file_remove'), hidden: 'hidden' });
        function refresh() {
            var f = state.files[q.id];
            info.textContent = f ? f.name : '';
            remove.hidden = !f;
        }
        input.addEventListener('change', function () {
            var f = input.files[0];
            if (!f) { return; }
            if (f.size > MAX_FILE) {
                alert(t('file_too_large'));
                input.value = '';
                return;
            }
            state.files[q.id] = { blob: f, name: f.name };
            refresh();
        });
        remove.addEventListener('click', function () {
            delete state.files[q.id];
            input.value = '';
            refresh();
        });
        refresh();
        return el('div', { class: 'q-upload' }, [input, info, remove]);
    };

    // Draw: opens a modal canvas powered by Signature Pad (vendored in /assets/vendor).
    renderers.draw = function (q) {
        var preview = el('img', { class: 'draw-preview', alt: '', hidden: 'hidden' });
        var button = el('button', { type: 'button', text: t('draw_open') });
        function refresh() {
            var f = state.files[q.id];
            preview.hidden = !f;
            if (f) { preview.src = f.url; }
            button.textContent = f ? t('draw_edit') : t('draw_open');
        }
        button.addEventListener('click', function () {
            openDrawModal(q, function (blob) {
                var old = state.files[q.id];
                if (old && old.url) { URL.revokeObjectURL(old.url); }
                state.files[q.id] = { blob: blob, name: 'drawing.png', url: URL.createObjectURL(blob) };
                refresh();
            });
        });
        refresh();
        return el('div', { class: 'q-draw' }, [button, preview]);
    };

    function openDrawModal(q, onSave) {
        var overlay = el('div', { class: 'modal-overlay' });
        var width = Math.min(900, window.innerWidth - 32);
        var height = Math.round(Math.min(width * 0.6, window.innerHeight - 160));
        var canvas = el('canvas', { class: 'draw-canvas' });
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        canvas.getContext('2d').scale(ratio, ratio);

        var pad = new window.SignaturePad(canvas, { penColor: '#111', minWidth: 0.8, maxWidth: 2.6 });
        if (state.drawData[q.id]) { pad.fromData(state.drawData[q.id]); }

        function close() { overlay.remove(); }
        var clear = el('button', { type: 'button', class: 'button-secondary', text: t('draw_clear') });
        var undo = el('button', { type: 'button', class: 'button-secondary', text: t('draw_undo') });
        var cancel = el('button', { type: 'button', class: 'button-secondary', text: t('draw_cancel') });
        var save = el('button', { type: 'button', text: t('draw_save') });
        clear.addEventListener('click', function () { pad.clear(); });
        undo.addEventListener('click', function () {
            var data = pad.toData();
            if (data.length) { data.pop(); pad.fromData(data); }
        });
        cancel.addEventListener('click', close);
        save.addEventListener('click', function () {
            if (pad.isEmpty()) { close(); return; }
            state.drawData[q.id] = pad.toData();
            // Export on a white background (the pad itself is transparent).
            var out = document.createElement('canvas');
            out.width = canvas.width;
            out.height = canvas.height;
            var ctx = out.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, out.width, out.height);
            ctx.drawImage(canvas, 0, 0);
            out.toBlob(function (blob) { if (blob) { onSave(blob); } close(); }, 'image/png');
        });

        overlay.appendChild(el('div', { class: 'modal' }, [
            el('div', { class: 'modal-body' }, [canvas]),
            el('div', { class: 'modal-actions' }, [clear, undo, cancel, save])
        ]));
        document.body.appendChild(overlay);
    }

    // ------------------------------------------------------------------
    // Rendering the exam
    // ------------------------------------------------------------------
    function renderExam() {
        main.innerHTML = '';
        var root = el('div', { class: 'exam-questions' });
        state.exam.questions.forEach(function (q, i) {
            var head = el('div', { class: 'q-head' }, [
                el('span', { class: 'q-num', text: (i + 1) + '.' }),
                el('span', { class: 'q-text', text: q.text }),
                el('span', { class: 'q-points', text: fmtPoints(q.points) })
            ]);
            var section = el('section', { class: 'q q-' + q.type, 'data-question-id': q.id, 'data-question-type': q.type }, [
                head,
                renderers[q.type](q)
            ]);
            root.appendChild(section);
        });
        main.appendChild(root);

        var errorBox = el('div', { class: 'flash flash-error', hidden: 'hidden', id: 'exam-submit-error' });
        var submit = el('button', { type: 'button', class: 'exam-submit', text: t('submit') });
        submit.addEventListener('click', function () { submitExam(submit, errorBox); });
        main.appendChild(errorBox);
        main.appendChild(el('div', { class: 'form-actions' }, [submit]));

        startForm.hidden = true;
        main.hidden = false;
        window.scrollTo(0, 0);
        Guard.init({ root: root, exam: state.exam, settings: state.exam.settings || {} });
    }

    // ------------------------------------------------------------------
    // Start / resume
    // ------------------------------------------------------------------
    function postForm(url, fd) {
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) { return res.json().catch(function () { return { ok: false }; }); });
    }

    function begin(data, resumed) {
        state.sid = data.submissionId;
        state.exam = data.exam;
        if (!resumed) { state.answers = {}; }
        renderExam();
        saveDraft();
        if (resumed) {
            main.insertBefore(el('div', { class: 'flash flash-success', text: t('resumed') }), main.firstChild);
        }
    }

    function start(fd, resumedAnswers) {
        fd.append('csrf_token', app.dataset.csrf);
        return postForm(app.dataset.startUrl, fd).then(function (data) {
            if (!data.ok) { throw new Error(data.error || 'start'); }
            if (resumedAnswers) { state.answers = resumedAnswers; }
            begin(data, !!resumedAnswers);
        });
    }

    startForm.addEventListener('submit', function (e) {
        e.preventDefault();
        startError.hidden = true;
        var fd = new FormData(startForm);
        start(fd, null).catch(function () {
            startError.textContent = t('error_start');
            startError.hidden = false;
        });
    });

    var draft = loadDraft();
    if (draft && draft.sid) {
        var fd = new FormData();
        fd.append('resume', draft.sid);
        start(fd, draft.answers || {}).catch(function () { clearDraft(); });
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------
    function submitExam(button, errorBox) {
        if (state.submitting) { return; }
        var unanswered = state.exam.questions.filter(function (q) { return !isAnswered(q); }).length;
        if (!confirm(unanswered ? t('unanswered') : t('confirm_submit'))) { return; }

        state.submitting = true;
        button.disabled = true;
        errorBox.hidden = true;

        var fd = new FormData();
        fd.append('csrf_token', app.dataset.csrf);
        fd.append('payload', JSON.stringify({ answers: state.answers, events: Guard.events }));
        Object.keys(state.files).forEach(function (qid) {
            fd.append('file_' + qid, state.files[qid].blob, state.files[qid].name);
        });

        postForm(app.dataset.submitUrl + encodeURIComponent(state.sid), fd).then(function (data) {
            if (!data.ok) { throw new Error(data.error || 'submit'); }
            state.done = true;
            clearDraft();
            main.innerHTML = '';
            main.appendChild(el('div', { class: 'exam-done' }, [
                el('h2', { text: t('submitted_title') }),
                el('p', { text: t('submitted_text') })
            ]));
            window.scrollTo(0, 0);
        }).catch(function () {
            state.submitting = false;
            button.disabled = false;
            errorBox.textContent = t('error_submit');
            errorBox.hidden = false;
        });
    }

    window.addEventListener('beforeunload', function (e) {
        if (state.exam && !state.done) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
