/* Teacher-side exam builder. Keeps questions in an array, renders editors,
 * and serialises everything into the hidden #questions-json field on submit.
 * The server re-validates and normalises everything (Exams::normalizeQuestions). */
(function () {
    'use strict';

    var data = JSON.parse(document.getElementById('exam-data').textContent) || [];
    var container = document.getElementById('questions');
    var form = document.getElementById('exam-form');
    var questions = data;

    var i18nNode = document.getElementById('builder-i18n');
    var I18N = i18nNode ? (JSON.parse(i18nNode.textContent) || {}) : {};
    function t(key, fallback) { return I18N['builder.' + key] || fallback; }

    var LABELS = {
        radio: t('type.radio', 'Radio'), checkbox: t('type.checkbox', 'Checkbox'), select: t('type.select', 'Select'),
        short_text: t('type.short_text', 'Short text'), number: t('type.number', 'Number'), long_text: t('type.long_text', 'Long text'),
        match: t('type.match', 'Connect A to B'), draw: t('type.draw', 'Draw'),
        code: t('type.code', 'Assignment'), essay: t('type.essay', 'Essay')
    };

    function el(tag, attrs, children) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'class') { n.className = attrs[k]; }
            else if (k === 'text') { n.textContent = attrs[k]; }
            else { n.setAttribute(k, attrs[k]); }
        });
        (children || []).forEach(function (c) { if (c) { n.appendChild(c); } });
        return n;
    }

    function btn(text, onClick, cls) {
        var b = el('button', { type: 'button', class: cls || 'button-secondary', text: text });
        b.addEventListener('click', onClick);
        return b;
    }

    function blank(type) {
        var q = { type: type, text: '', points: 1 };
        if (type === 'radio' || type === 'checkbox' || type === 'select') {
            q.options = [{ text: '', correct: false }, { text: '', correct: false }];
        } else if (type === 'short_text' || type === 'number') {
            q.accepted = [];
            if (type === 'number') { q.tolerance = 0; }
        } else if (type === 'match') {
            q.pairs = [{ left: '', right: '' }, { left: '', right: '' }];
        } else if (type === 'code') {
            q.points = 10;
            q.description = '';
            q.language = 'python';
            q.starter = '';
            q.chat = false;
        }
        return q;
    }

    function move(i, d) {
        var j = i + d;
        if (j < 0 || j >= questions.length) { return; }
        var tmp = questions[i]; questions[i] = questions[j]; questions[j] = tmp;
        render();
    }

    function textInput(value, placeholder, onChange, cls) {
        var i = el('input', { type: 'text', placeholder: placeholder || '', class: cls || '' });
        i.value = value || '';
        i.addEventListener('input', function () { onChange(i.value); });
        return i;
    }

    // --- type specific editors ---------------------------------------

    function optionsEditor(q) {
        var multi = q.type === 'checkbox';
        var wrap = el('div', { class: 'opts' });
        var hint = multi ? t('hint_multi', 'Tick every correct option (optional).') : t('hint_single', 'Tick the correct option; tick several to accept any of them (optional).');
        wrap.appendChild(el('p', { class: 'muted', text: hint }));
        q.options.forEach(function (o, idx) {
            var cb = el('input', { type: 'checkbox' });
            cb.checked = !!o.correct;
            cb.addEventListener('change', function () { o.correct = cb.checked; });
            wrap.appendChild(el('div', { class: 'opt-row' }, [
                cb,
                textInput(o.text, t('option', 'Option') + ' ' + (idx + 1), function (v) { o.text = v; }),
                btn('✕', function () { q.options.splice(idx, 1); render(); }, 'button-secondary small')
            ]));
        });
        wrap.appendChild(btn(t('add_option', '+ Option'), function () { q.options.push({ text: '', correct: false }); render(); }));
        return wrap;
    }

    function acceptedEditor(q) {
        var wrap = el('div', { class: 'opts' });
        wrap.appendChild(el('p', { class: 'muted', text: q.type === 'short_text' ? t('accepted_hint_text', 'Accepted correct answers (optional, any of them counts; case and extra spaces are ignored).') : t('accepted_hint', 'Accepted correct answers (optional, any of them counts).') }));
        q.accepted.forEach(function (a, idx) {
            wrap.appendChild(el('div', { class: 'opt-row' }, [
                textInput(a, t('accepted_answer', 'Accepted answer'), function (v) { q.accepted[idx] = v; }),
                btn('✕', function () { q.accepted.splice(idx, 1); render(); }, 'button-secondary small')
            ]));
        });
        wrap.appendChild(btn(t('add_accepted', '+ Accepted answer'), function () { q.accepted.push(''); render(); }));
        if (q.type === 'number') {
            var tol = el('input', { type: 'number', step: 'any', min: '0' });
            tol.value = q.tolerance || 0;
            tol.addEventListener('input', function () { q.tolerance = parseFloat(tol.value) || 0; });
            wrap.appendChild(el('label', { class: 'inline-label' }, [document.createTextNode(t('tolerance', 'Tolerance') + ' ±'), tol]));
        }
        return wrap;
    }

    function pairsEditor(q) {
        var wrap = el('div', { class: 'opts' });
        wrap.appendChild(el('p', { class: 'muted', text: t('pairs_hint', 'Enter the correct pairs. Students see both columns shuffled (differently for each student).') }));
        q.pairs.forEach(function (p, idx) {
            wrap.appendChild(el('div', { class: 'opt-row' }, [
                textInput(p.left, 'A', function (v) { p.left = v; }),
                el('span', { text: '→' }),
                textInput(p.right, 'B', function (v) { p.right = v; }),
                btn('✕', function () { q.pairs.splice(idx, 1); render(); }, 'button-secondary small')
            ]));
        });
        wrap.appendChild(btn(t('add_pair', '+ Pair'), function () { q.pairs.push({ left: '', right: '' }); render(); }));
        return wrap;
    }

    // Code assignment: title, language, Markdown description, starting code (with the
    // same highlighting editor the students get), and the reserved chatbot switch.
    var editors = [];
    function codeEditor(q) {
        var wrap = el('div', { class: 'opts code-opts' });

        var lang = el('select', {}, [
            el('option', { value: 'python', text: 'Python' }),
            el('option', { value: 'javascript', text: 'JavaScript' })
        ]);
        lang.value = q.language || 'python';
        var holder = el('div', { class: 'code-editor code-editor-builder' });
        var editor = null;
        function mount() {
            if (editor) { editor.destroy(); editors.splice(editors.indexOf(editor), 1); }
            holder.innerHTML = '';
            if (!window.MathubCode) {
                var fallback = el('textarea', { rows: '8', class: 'mono' });
                fallback.value = q.starter || '';
                fallback.addEventListener('input', function () { q.starter = fallback.value; });
                holder.appendChild(fallback);
                return;
            }
            editor = window.MathubCode.create(holder, {
                doc: q.starter || '', language: q.language || 'python', label: t('code_starter', 'Starting code'),
                onChange: function (v) { q.starter = v; }
            });
            editors.push(editor);
        }
        lang.addEventListener('change', function () { q.language = lang.value; mount(); });

        var desc = el('textarea', { rows: '8', class: 'mono', placeholder: t('code_description_ph', 'Assignment text in Markdown…') });
        desc.value = q.description || '';
        desc.addEventListener('input', function () { q.description = desc.value; });

        var chat = el('input', { type: 'checkbox', disabled: 'disabled' });
        chat.checked = !!q.chat;

        wrap.appendChild(el('label', { class: 'inline-label' }, [document.createTextNode(t('code_language', 'Language')), lang]));
        wrap.appendChild(el('label', {}, [document.createTextNode(t('code_description', 'Description (Markdown) – shown in the “Zadání” tab')), desc]));
        wrap.appendChild(el('p', { class: 'muted', text: t('code_starter_hint', 'Starting code – shown in the “Kód” tab (optional).') }));
        wrap.appendChild(holder);
        wrap.appendChild(el('label', { class: 'inline-label' }, [chat, document.createTextNode(' ' + t('code_chat', 'Chatbot assistant (coming soon)'))]));
        mount();
        return wrap;
    }

    function render() {
        editors.forEach(function (e) { e.destroy(); });
        editors = [];
        container.innerHTML = '';
        questions.forEach(function (q, i) {
            var isCode = q.type === 'code';
            var isEssay = q.type === 'essay';
            var text = isCode
                ? el('input', { type: 'text', maxlength: '200', placeholder: t('code_title', 'Assignment title') })
                : el('textarea', { rows: isEssay ? '5' : '2', placeholder: isEssay ? t('essay_prompt', 'Essay prompt (what the student should write about)') : t('question_text', 'Question text') });
            text.value = q.text || '';
            text.addEventListener('input', function () { q.text = text.value; });

            var pts = el('input', { type: 'number', step: '0.5', min: '0', class: 'pts' });
            pts.value = q.points;
            pts.addEventListener('input', function () { q.points = parseFloat(pts.value) || 0; });

            var body = null;
            if (isCode) { body = codeEditor(q); }
            else if (q.options) { body = optionsEditor(q); }
            else if (q.accepted) { body = acceptedEditor(q); }
            else if (q.pairs) { body = pairsEditor(q); }
            else if (isEssay) { body = el('p', { class: 'muted', text: t('essay_hint', 'The student writes one long document on a single page. Scored by hand.') }); }
            else { body = el('p', { class: 'muted', text: t('manual', 'Scored by hand.') }); }

            container.appendChild(el('div', { class: 'qcard' }, [
                el('div', { class: 'qcard-head' }, [
                    el('strong', { text: (i + 1) + '. ' + LABELS[q.type] }),
                    el('span', { class: 'spacer' }),
                    el('label', { class: 'inline-label' }, [document.createTextNode(t('points', 'Points')), pts]),
                    btn('↑', function () { move(i, -1); }, 'button-secondary small'),
                    btn('↓', function () { move(i, 1); }, 'button-secondary small'),
                    btn(t('delete', 'Delete'), function () {
                        if (confirm(t('confirm_delete', 'Delete this question?'))) { questions.splice(i, 1); render(); }
                    }, 'danger small')
                ]),
                text,
                body
            ]));
        });
        syncAdd();
    }

    var addButton = document.getElementById('add-question');
    // Essay content is one document: once it has its question there is nothing more to add.
    function syncAdd() { if (addButton.dataset.single) { addButton.style.display = questions.length > 0 ? 'none' : ''; } }
    addButton.addEventListener('click', function () {
        if (addButton.dataset.single && questions.length) { return; }
        questions.push(blank(document.getElementById('add-type').value));
        render();
        var cards = container.querySelectorAll('.qcard');
        if (cards.length) { cards[cards.length - 1].scrollIntoView({ block: 'center' }); }
    });

    form.addEventListener('submit', function () {
        document.getElementById('questions-json').value = JSON.stringify(questions);
    });

    render();
})();
