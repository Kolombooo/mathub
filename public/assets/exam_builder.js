/* Teacher-side exam builder. Keeps questions in an array, renders editors,
 * and serialises everything into the hidden #questions-json field on submit.
 * The server re-validates and normalises everything (Exams::normalizeQuestions). */
(function () {
    'use strict';

    var data = JSON.parse(document.getElementById('exam-data').textContent) || [];
    var container = document.getElementById('questions');
    var form = document.getElementById('exam-form');
    var questions = data;

    var LABELS = {
        radio: 'Radio', checkbox: 'Checkbox', select: 'Select', short_text: 'Short text',
        number: 'Number', long_text: 'Long text', match: 'Connect A to B', upload: 'Upload file', draw: 'Draw'
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
        var hint = multi ? 'Tick every correct option (optional).' : 'Tick the correct option; tick several to accept any of them (optional).';
        wrap.appendChild(el('p', { class: 'muted', text: hint }));
        q.options.forEach(function (o, idx) {
            var cb = el('input', { type: 'checkbox' });
            cb.checked = !!o.correct;
            cb.addEventListener('change', function () { o.correct = cb.checked; });
            wrap.appendChild(el('div', { class: 'opt-row' }, [
                cb,
                textInput(o.text, 'Option ' + (idx + 1), function (v) { o.text = v; }),
                btn('✕', function () { q.options.splice(idx, 1); render(); }, 'button-secondary small')
            ]));
        });
        wrap.appendChild(btn('+ Option', function () { q.options.push({ text: '', correct: false }); render(); }));
        return wrap;
    }

    function acceptedEditor(q) {
        var wrap = el('div', { class: 'opts' });
        wrap.appendChild(el('p', { class: 'muted', text: 'Accepted correct answers (optional, any of them counts' + (q.type === 'short_text' ? '; case and extra spaces are ignored' : '') + ').' }));
        q.accepted.forEach(function (a, idx) {
            wrap.appendChild(el('div', { class: 'opt-row' }, [
                textInput(a, 'Accepted answer', function (v) { q.accepted[idx] = v; }),
                btn('✕', function () { q.accepted.splice(idx, 1); render(); }, 'button-secondary small')
            ]));
        });
        wrap.appendChild(btn('+ Accepted answer', function () { q.accepted.push(''); render(); }));
        if (q.type === 'number') {
            var tol = el('input', { type: 'number', step: 'any', min: '0' });
            tol.value = q.tolerance || 0;
            tol.addEventListener('input', function () { q.tolerance = parseFloat(tol.value) || 0; });
            wrap.appendChild(el('label', { class: 'inline-label' }, [document.createTextNode('Tolerance ±'), tol]));
        }
        return wrap;
    }

    function pairsEditor(q) {
        var wrap = el('div', { class: 'opts' });
        wrap.appendChild(el('p', { class: 'muted', text: 'Enter the correct pairs. Students see both columns shuffled (differently for each student).' }));
        q.pairs.forEach(function (p, idx) {
            wrap.appendChild(el('div', { class: 'opt-row' }, [
                textInput(p.left, 'A', function (v) { p.left = v; }),
                el('span', { text: '→' }),
                textInput(p.right, 'B', function (v) { p.right = v; }),
                btn('✕', function () { q.pairs.splice(idx, 1); render(); }, 'button-secondary small')
            ]));
        });
        wrap.appendChild(btn('+ Pair', function () { q.pairs.push({ left: '', right: '' }); render(); }));
        return wrap;
    }

    function render() {
        container.innerHTML = '';
        questions.forEach(function (q, i) {
            var text = el('textarea', { rows: '2', placeholder: 'Question text' });
            text.value = q.text || '';
            text.addEventListener('input', function () { q.text = text.value; });

            var pts = el('input', { type: 'number', step: '0.5', min: '0', class: 'pts' });
            pts.value = q.points;
            pts.addEventListener('input', function () { q.points = parseFloat(pts.value) || 0; });

            var body = null;
            if (q.options) { body = optionsEditor(q); }
            else if (q.accepted) { body = acceptedEditor(q); }
            else if (q.pairs) { body = pairsEditor(q); }
            else { body = el('p', { class: 'muted', text: 'Scored by hand.' }); }

            container.appendChild(el('div', { class: 'qcard' }, [
                el('div', { class: 'qcard-head' }, [
                    el('strong', { text: (i + 1) + '. ' + LABELS[q.type] }),
                    el('span', { class: 'spacer' }),
                    el('label', { class: 'inline-label' }, [document.createTextNode('Points'), pts]),
                    btn('↑', function () { move(i, -1); }, 'button-secondary small'),
                    btn('↓', function () { move(i, 1); }, 'button-secondary small'),
                    btn('Delete', function () {
                        if (confirm('Delete this question?')) { questions.splice(i, 1); render(); }
                    }, 'danger small')
                ]),
                text,
                body
            ]));
        });
    }

    document.getElementById('add-question').addEventListener('click', function () {
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
