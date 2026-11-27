/*
 * Essay exams (student side). Loaded by exam.php for exams whose content kind is
 * "essay"; exam.js calls MathubExamEssay.render() instead of building the usual
 * question list.
 *
 * One question, one page: the prompt, a small formatting toolbar and a paper-like
 * contenteditable. The answer is stored as HTML (state.answers[qid]); the server
 * rebuilds it from an allow-list (Exams::cleanEssayHtml), so everything the
 * toolbar can produce must be understood there too.
 *
 * Paste policy (the "closed book" part):
 *   - Text may only be pasted if it was copied from this site (anywhere inside
 *     the exam page). On copy/cut we remember what the clipboard should hold; a
 *     paste is accepted only if the clipboard text is exactly that.
 *   - Pictures can always be pasted/dropped, unless they come with text (e.g. a
 *     Word or web selection that also carries an image rendering of the text).
 *   - Anything else is dropped and, when monitoring is on, logged as
 *     "paste_blocked" for the teacher.
 * This is a browser-side best effort, like the other exam guards: it keeps honest
 * students honest and leaves evidence, it cannot stop someone typing text in.
 */
(function () {
    'use strict';

    var FONTS = ['Arial', 'Verdana', 'Trebuchet MS', 'Georgia', 'Times New Roman', 'Courier New'];
    // execCommand('fontSize') values -> labels (the browser writes them as CSS keywords).
    var SIZES = [['2', 'size_small'], ['3', 'size_normal'], ['4', 'size_large'], ['5', 'size_larger'], ['6', 'size_huge']];
    var MAX_IMAGE_SIDE = 1400;
    var MAX_HTML = 6 * 1000 * 1000; // keep clear of the server cap (8 MB)
    var COPY_KEY = 'mathub-essay-copy';

    var ALLOWED = { P: 1, DIV: 1, BR: 1, B: 1, STRONG: 1, I: 1, EM: 1, U: 1, S: 1, STRIKE: 1, SUB: 1, SUP: 1, UL: 1, OL: 1, LI: 1, H1: 1, H2: 1, H3: 1, BLOCKQUOTE: 1, SPAN: 1, IMG: 1 };
    var DROP = { SCRIPT: 1, STYLE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, NOSCRIPT: 1, TEMPLATE: 1, SVG: 1, MATH: 1, FORM: 1, SELECT: 1, TEXTAREA: 1, BUTTON: 1, HEAD: 1, TITLE: 1, LINK: 1, META: 1 };
    var STYLE_PROPS = ['font-family', 'font-size', 'text-align', 'font-weight', 'font-style', 'text-decoration'];

    function norm(s) { return String(s || '').replace(/\s+/g, ' ').trim(); }

    // Keeps only what the toolbar can make; used for text copied inside the site and for restored drafts.
    function sanitize(html) {
        var doc = new DOMParser().parseFromString('<body>' + html, 'text/html');
        (function walk(node) {
            Array.prototype.slice.call(node.childNodes).forEach(function (c) {
                if (c.nodeType === 3) { return; }
                if (c.nodeType !== 1) { c.remove(); return; }
                if (DROP[c.tagName]) { c.remove(); return; }
                if (!ALLOWED[c.tagName]) {
                    walk(c);
                    while (c.firstChild) { c.parentNode.insertBefore(c.firstChild, c); }
                    c.remove();
                    return;
                }
                var keep = {};
                STYLE_PROPS.forEach(function (p) { var v = c.style.getPropertyValue(p); if (v) { keep[p] = v; } });
                var src = c.tagName === 'IMG' ? c.getAttribute('src') || '' : '';
                Array.prototype.slice.call(c.attributes).forEach(function (a) { c.removeAttribute(a.name); });
                Object.keys(keep).forEach(function (p) { c.style.setProperty(p, keep[p]); });
                if (c.tagName === 'IMG') {
                    if (/^data:image\/(png|jpeg|gif|webp);base64,[A-Za-z0-9+\/=]+$/.test(src)) { c.setAttribute('src', src); c.setAttribute('alt', ''); }
                    else { c.remove(); return; }
                }
                walk(c);
            });
        })(doc.body);
        return doc.body.innerHTML;
    }

    function render(o) {
        var el = o.el, t = o.t, q = o.state.exam.questions[0];
        var state = o.state;

        // ---- what was last copied from this page --------------------------
        var last = null;
        try { last = JSON.parse(sessionStorage.getItem(COPY_KEY) || 'null'); } catch (e) { last = null; }
        function remember(text, html) {
            last = { text: norm(text), html: html };
            try { sessionStorage.setItem(COPY_KEY, JSON.stringify(last)); } catch (e) { /* private mode: in-memory copy still works */ }
        }

        // ---- layout ---------------------------------------------------------
        var editor = el('div', {
            class: 'essay-paper essay-editor', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true',
            'aria-label': q.text || t('essay_label'), 'data-placeholder': t('essay_placeholder'), spellcheck: 'true'
        });
        var notice = el('div', { class: 'essay-notice', role: 'status', hidden: 'hidden' });
        var counts = el('span', { class: 'essay-counts' });
        var status = el('div', { class: 'essay-status' }, [counts]);
        var toolbar = el('div', { class: 'essay-toolbar', role: 'toolbar', 'aria-label': t('essay_toolbar') });

        var saved = state.answers[q.id];
        editor.innerHTML = typeof saved === 'string' ? sanitize(saved) : '';

        // ---- selection bookkeeping (toolbar selects steal focus) -----------
        var range = null;
        document.addEventListener('selectionchange', function () {
            var s = window.getSelection();
            if (s && s.rangeCount && editor.contains(s.anchorNode)) { range = s.getRangeAt(0).cloneRange(); refreshToolbar(); }
        });
        function focusEditor() {
            editor.focus();
            if (range) {
                var s = window.getSelection();
                s.removeAllRanges();
                s.addRange(range);
            }
        }
        function exec(cmd, val) {
            focusEditor();
            try { document.execCommand('styleWithCSS', false, true); } catch (e) { /* older browsers */ }
            document.execCommand(cmd, false, val === undefined ? null : val);
            changed();
        }
        function insertHtml(html) {
            focusEditor();
            document.execCommand('insertHTML', false, html);
            changed();
        }

        // ---- counts + saving -------------------------------------------------
        function plain() { return editor.innerText || ''; }
        function hasContent() { return norm(plain()) !== '' || !!editor.querySelector('img'); }
        function updateCounts() {
            var text = plain();
            var words = (text.match(/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/gu) || []).length;
            var letters = (text.match(/\p{L}/gu) || []).length;
            counts.textContent = t('essay_words') + ': ' + words + '  ·  ' + t('essay_letters') + ': ' + letters;
            editor.classList.toggle('is-empty', !hasContent());
        }
        function changed() {
            o.setAnswer(q.id, hasContent() ? editor.innerHTML : '');
            updateCounts();
        }
        editor.addEventListener('input', changed);

        // ---- toolbar ---------------------------------------------------------
        function tbButton(label, title, cmd, val, extra) {
            var b = el('button', { type: 'button', class: 'essay-btn' + (extra ? ' ' + extra : ''), title: title, 'aria-label': title, 'data-cmd': cmd });
            b.innerHTML = label;
            b.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep the selection
            b.addEventListener('click', function () { exec(cmd, val); });
            return b;
        }
        function group(children) { return el('div', { class: 'essay-group' }, children); }
        function sel(title, options, onChange) {
            var s = el('select', { class: 'essay-select', title: title, 'aria-label': title });
            options.forEach(function (op) { s.appendChild(el('option', { value: op[0], text: op[1] })); });
            s.addEventListener('change', function () { onChange(s.value); s.value = ''; focusEditor(); });
            return s;
        }
        function placeholderOption(label, options) { return [['', label]].concat(options); }

        var block = sel(t('essay_style'), placeholderOption(t('essay_style'), [['p', t('essay_normal')], ['h1', t('essay_h1')], ['h2', t('essay_h2')], ['h3', t('essay_h3')], ['blockquote', t('essay_quote')]]),
            function (v) { if (v) { exec('formatBlock', v); } });
        var font = sel(t('essay_font'), placeholderOption(t('essay_font'), FONTS.map(function (f) { return [f, f]; })),
            function (v) { if (v) { exec('fontName', v); } });
        var size = sel(t('essay_size'), placeholderOption(t('essay_size'), SIZES.map(function (s) { return [s[0], t(s[1])]; })),
            function (v) { if (v) { exec('fontSize', v); } });
        Array.prototype.forEach.call([block, font, size], function (s) {
            s.querySelector('option[value=""]').disabled = true;
            s.value = '';
        });

        toolbar.appendChild(group([block, font, size]));
        toolbar.appendChild(group([
            tbButton('<b>B</b>', t('essay_bold'), 'bold'),
            tbButton('<i>I</i>', t('essay_italic'), 'italic'),
            tbButton('<u>U</u>', t('essay_underline'), 'underline'),
            tbButton('<s>S</s>', t('essay_strike'), 'strikeThrough')
        ]));
        toolbar.appendChild(group([
            tbButton('x<sup>2</sup>', t('essay_sup'), 'superscript'),
            tbButton('x<sub>2</sub>', t('essay_sub'), 'subscript')
        ]));
        toolbar.appendChild(group([
            tbButton('&bull;&nbsp;&mdash;', t('essay_ul'), 'insertUnorderedList'),
            tbButton('1.&nbsp;&mdash;', t('essay_ol'), 'insertOrderedList')
        ]));
        toolbar.appendChild(group([
            tbButton('&#8676;', t('essay_left'), 'justifyLeft'),
            tbButton('&#8596;', t('essay_center'), 'justifyCenter'),
            tbButton('&#8677;', t('essay_right'), 'justifyRight'),
            tbButton('&#8801;', t('essay_justify'), 'justifyFull')
        ]));
        toolbar.appendChild(group([
            tbButton('&#8630;', t('essay_undo'), 'undo'),
            tbButton('&#8631;', t('essay_redo'), 'redo'),
            tbButton('T<small>x</small>', t('essay_clear'), 'removeFormat')
        ]));
        function refreshToolbar() {
            toolbar.querySelectorAll('button[data-cmd]').forEach(function (b) {
                var on = false;
                try { on = document.queryCommandState(b.getAttribute('data-cmd')); } catch (e) { on = false; }
                b.classList.toggle('active', !!on);
            });
        }

        // ---- copy / cut: remember what belongs to this site ----------------
        function onCopy(e) {
            var s = window.getSelection();
            if (!s || s.isCollapsed || !s.rangeCount || !o.app.contains(s.anchorNode)) { return; }
            var tag = e.target && e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA') { return; }
            var holder = document.createElement('div');
            holder.appendChild(s.getRangeAt(0).cloneContents());
            var text = s.toString();
            var html = sanitize(holder.innerHTML);
            remember(text, html);
            if (e.clipboardData) {
                e.clipboardData.setData('text/plain', text);
                e.clipboardData.setData('text/html', html);
                e.preventDefault();
                if (e.type === 'cut' && editor.contains(s.anchorNode)) { document.execCommand('delete'); changed(); }
            }
        }
        document.addEventListener('copy', onCopy);
        document.addEventListener('cut', onCopy);

        // ---- paste -----------------------------------------------------------
        var noticeTimer = null;
        function blocked(chars) {
            notice.textContent = t('essay_paste_blocked');
            notice.hidden = false;
            clearTimeout(noticeTimer);
            noticeTimer = setTimeout(function () { notice.hidden = true; }, 6000);
            if (o.log) { o.log('paste_blocked', { chars: chars }); }
        }
        function imageFiles(dt) {
            var out = [];
            if (!dt) { return out; }
            Array.prototype.forEach.call(dt.files || [], function (f) { if (/^image\//.test(f.type)) { out.push(f); } });
            if (!out.length) {
                Array.prototype.forEach.call(dt.items || [], function (it) {
                    var f = it.kind === 'file' && /^image\//.test(it.type) ? it.getAsFile() : null;
                    if (f) { out.push(f); }
                });
            }
            return out;
        }
        function addImage(file) {
            var reader = new FileReader();
            reader.onload = function () {
                var img = new Image();
                img.onload = function () {
                    var k = Math.min(1, MAX_IMAGE_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(img.naturalWidth * k));
                    canvas.height = Math.max(1, Math.round(img.naturalHeight * k));
                    var g = canvas.getContext('2d');
                    g.fillStyle = '#ffffff';
                    g.fillRect(0, 0, canvas.width, canvas.height);
                    g.drawImage(img, 0, 0, canvas.width, canvas.height);
                    var url = canvas.toDataURL('image/jpeg', 0.85);
                    if (editor.innerHTML.length + url.length > MAX_HTML) {
                        o.showDialog(t('essay_image_too_big'), t('dialog_ok'), null);
                        return;
                    }
                    focusEditor();
                    document.execCommand('insertImage', false, url);
                    changed();
                };
                img.onerror = function () { o.showDialog(t('essay_image_failed'), t('dialog_ok'), null); };
                img.src = reader.result;
            };
            reader.readAsDataURL(file);
        }

        editor.addEventListener('paste', function (e) {
            e.preventDefault();
            var cd = e.clipboardData;
            if (!cd) { return; }
            var text = cd.getData('text/plain') || '';
            var files = imageFiles(cd);
            if (norm(text) === '') {
                if (files.length) { files.forEach(addImage); return; }
                // A picture copied from this very page (no file, no text, just our own markup).
                if (last && last.text === '' && /<img/i.test(cd.getData('text/html') || '')) { insertHtml(last.html); }
                return;
            }
            if (last && norm(text) === last.text) { insertHtml(last.html); return; }
            blocked(text.length);
        });

        // Dragging text in from another window is the same as pasting it.
        var internalDrag = false;
        document.addEventListener('dragstart', function (e) { internalDrag = !!(e.target && o.app.contains(e.target)); });
        document.addEventListener('dragend', function () { internalDrag = false; });
        editor.addEventListener('drop', function (e) {
            if (internalDrag) { internalDrag = false; return; }
            e.preventDefault();
            var dt = e.dataTransfer;
            var files = imageFiles(dt);
            if (files.length && norm(dt.getData('text/plain')) === '') { files.forEach(addImage); return; }
            blocked((dt && dt.getData('text/plain') || '').length);
        });

        // ---- assemble ---------------------------------------------------------
        editor.addEventListener('focus', function () {
            try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* ignore */ }
        });

        var head = el('div', { class: 'q-head essay-head' }, [
            el('span', { class: 'q-text', text: q.text }),
            el('span', { class: 'q-points', text: o.fmtPoints(q.points) })
        ]);
        var section = el('section', { class: 'q q-essay', 'data-question-id': q.id, 'data-question-type': 'essay' }, [
            head,
            toolbar,
            notice,
            el('div', { class: 'essay-sheet' }, [editor]),
            status
        ]);
        editor.classList.add('exam-input');

        var root = el('div', { class: 'exam-essay' }, [section, o.errorBox, el('div', { class: 'form-actions' }, [o.submitButton])]);
        document.body.classList.add('page-exam-essay');
        updateCounts();
        return root;
    }

    window.MathubExamEssay = { render: render };
})();
