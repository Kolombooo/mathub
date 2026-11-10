/*
 * Student exam client.
 *
 * The exam is a single page: the server sends the questions once (POST /start),
 * all answers live in `state` in the browser (and in a localStorage draft so a
 * refresh does not lose work), and everything is sent back in ONE multipart
 * POST at the end. No polling, no websockets.
 *
 * ---------------------------------------------------------------------
 * Anti-cheating: window.MathubExamGuard
 * ---------------------------------------------------------------------
 * A tiny registry; the actual guards live in exam_guard.js (page visibility,
 * fullscreen, browser close). A guard module is an object with any of:
 *     name
 *     onUserStart()        synchronously inside the "Start" click (the only
 *                          place requestFullscreen() is allowed)
 *     init(ctx)            exam is rendered. ctx = { root, exam, settings,
 *                          log(type, detail, atMs), beacon(type, detail),
 *                          resumed, lastSeen, suspendBlur(), t(key) }  (suspendBlur: call before opening a
 *                          native dialog such as a file picker)
 *     onBeforeSubmit()     close any open "episodes" so they are logged
 *     onFinish()           exam submitted: clean up (leave fullscreen, ...)
 * Every question is a  <section data-question-id data-question-type>  and every
 * answer control has class "exam-input", so a later module (e.g. anti-paste)
 * can listen on ctx.root with event delegation.
 * Events are {type, at (unix s), detail}. They are kept in the localStorage
 * draft (so a reload does not erase them), sent with the final submission and
 * shown in red on the teacher's submission page. "page_closed" is the one
 * event sent immediately (navigator.sendBeacon, server timestamp) because the
 * page is going away.
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
    var settings = {};
    try { settings = JSON.parse(app.dataset.settings || '{}') || {}; } catch (e) { settings = {}; }

    var Guard = window.MathubExamGuard = {
        events: [],
        modules: [],
        settings: settings,
        active: false,
        register: function (mod) { Guard.modules.push(mod); },
        log: function (type, detail, atMs) {
            Guard.events.push({ type: String(type), at: Math.floor((atMs || Date.now()) / 1000), detail: detail === undefined ? null : detail });
            saveDraftNow();
        },
        call: function (hook, arg) {
            Guard.modules.forEach(function (m) {
                if (typeof m[hook] !== 'function') { return; }
                try { m[hook](arg); } catch (e) { /* a broken guard must never break the exam */ }
            });
        },
        init: function (ctx) {
            Guard.active = true;
            ctx.log = Guard.log;
            ctx.beacon = sendBeacon;
            ctx.t = t;
            ctx.suspendBlur = suspendBlur;
            ctx.settings = settings;
            Guard.call('init', ctx);
        }
    };

    // Native dialogs (confirm, file picker) steal window focus; those must not
    // count as "left the page". Modules read Guard.blurSuspended.
    Guard.blurSuspended = false;
    function suspendBlur(maxMs) {
        Guard.blurSuspended = true;
        var release = function () {
            window.removeEventListener('focus', release);
            setTimeout(function () { Guard.blurSuspended = false; window.dispatchEvent(new Event('mathub-guard-recheck')); }, 300);
        };
        window.addEventListener('focus', release);
        // Safety net if focus never fires (some browsers don't blur at all).
        setTimeout(release, maxMs || 60000);
    }

    function sendBeacon(type, detail) {
        var fd = new FormData();
        fd.append('csrf_token', app.dataset.csrf);
        fd.append('type', type);
        fd.append('detail', JSON.stringify(detail || null));
        try {
            if (!(navigator.sendBeacon && navigator.sendBeacon(app.dataset.eventUrl + encodeURIComponent(state.sid), fd))) {
                fetch(app.dataset.eventUrl + encodeURIComponent(state.sid), { method: 'POST', body: fd, keepalive: true, credentials: 'same-origin' });
            }
        } catch (e) { /* nothing more we can do while the page is closing */ }
    }

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
    function writeDraft() {
        if (!state.sid || state.done) { return; }
        try {
            localStorage.setItem(storageKey, JSON.stringify({
                sid: state.sid, answers: state.answers, events: Guard.events, lastSeen: Date.now()
            }));
        } catch (e) { /* storage full/blocked: fine */ }
    }
    function saveDraft() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(writeDraft, 300);
    }
    function saveDraftNow() {
        clearTimeout(saveTimer);
        writeDraft();
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

    // In-page replacement for confirm()/alert(). Native dialogs do not render
    // over a fullscreen page in Firefox (the page just looks frozen), so
    // everything the student has to answer is drawn inside the document.
    // Resolves true/false. `okLabel`/`cancelLabel`: cancelLabel null = alert-style.
    function showDialog(message, okLabel, cancelLabel) {
        return new Promise(function (resolve) {
            var overlay = el('div', { class: 'modal-overlay dialog-overlay' });
            var ok = el('button', { type: 'button', text: okLabel });
            var actions = el('div', { class: 'modal-actions' });
            var previous = document.activeElement;
            function done(result) {
                document.removeEventListener('keydown', onKey, true);
                overlay.remove();
                if (previous && previous.focus) { previous.focus(); }
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(false); }
            }
            ok.addEventListener('click', function () { done(true); });
            if (cancelLabel) {
                var cancel = el('button', { type: 'button', class: 'button-secondary', text: cancelLabel });
                cancel.addEventListener('click', function () { done(false); });
                actions.appendChild(cancel);
            }
            actions.appendChild(ok);
            overlay.appendChild(el('div', { class: 'modal dialog', role: 'dialog', 'aria-modal': 'true' }, [
                el('p', { class: 'dialog-text', text: message }),
                actions
            ]));
            document.addEventListener('keydown', onKey, true);
            document.body.appendChild(overlay);
            ok.focus();
        });
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

    // Connect A to B: two shuffled columns (shuffled once per student). Draw a
    // line by dragging from a dot to an item on the other side, or by clicking
    // an item on one side and then one on the other. Click a line to remove it.
    // The answer is stored as { leftId: rightId } (one-to-one).
    var SVGNS = 'http://www.w3.org/2000/svg';

    renderers.match = function (q) {
        var lefts = shuffle(q.lefts);
        var rights = shuffle(q.rights);
        var pairs = state.answers[q.id] || {};
        var selected = null; // { side: 'l'|'r', id }
        var items = { l: {}, r: {} };

        var board = el('div', { class: 'match-board' });
        var svg = document.createElementNS(SVGNS, 'svg');
        svg.setAttribute('class', 'match-lines');
        var colL = el('div', { class: 'match-col match-col-l' });
        var colR = el('div', { class: 'match-col match-col-r' });

        function dotOf(side, id) { return items[side][id].querySelector('.match-dot'); }

        function connect(l, r) {
            Object.keys(pairs).forEach(function (k) { if (k === l || pairs[k] === r) { delete pairs[k]; } });
            pairs[l] = r;
            commit();
        }
        function commit() {
            setAnswer(q.id, pairs);
            draw();
        }

        function center(dot) {
            var b = board.getBoundingClientRect();
            var d = dot.getBoundingClientRect();
            return { x: d.left + d.width / 2 - b.left, y: d.top + d.height / 2 - b.top };
        }
        function curve(a, b) {
            var dx = Math.max(40, Math.abs(b.x - a.x) / 2);
            return 'M' + a.x + ',' + a.y + ' C' + (a.x + dx) + ',' + a.y + ' ' + (b.x - dx) + ',' + b.y + ' ' + b.x + ',' + b.y;
        }
        function path(d, cls) {
            var p = document.createElementNS(SVGNS, 'path');
            p.setAttribute('d', d);
            p.setAttribute('class', cls);
            return p;
        }

        function draw() {
            svg.innerHTML = '';
            Object.keys(pairs).forEach(function (l) {
                var r = pairs[l];
                if (!items.l[l] || !items.r[r]) { return; }
                var d = curve(center(dotOf('l', l)), center(dotOf('r', r)));
                var hit = path(d, 'match-line-hit');
                hit.addEventListener('click', function () { delete pairs[l]; commit(); });
                svg.appendChild(path(d, 'match-line'));
                svg.appendChild(hit);
            });
            Object.keys(items).forEach(function (side) {
                Object.keys(items[side]).forEach(function (id) {
                    var linked = side === 'l' ? pairs[id] !== undefined : Object.keys(pairs).some(function (k) { return pairs[k] === id; });
                    items[side][id].classList.toggle('linked', linked);
                    items[side][id].classList.toggle('selected', !!selected && selected.side === side && selected.id === id);
                });
            });
        }

        function pick(side, id) {
            if (selected && selected.side !== side) {
                var l = side === 'r' ? selected.id : id;
                var r = side === 'r' ? id : selected.id;
                selected = null;
                connect(l, r);
            } else if (selected && selected.side === side && selected.id === id) {
                selected = null;
                draw();
            } else {
                selected = { side: side, id: id };
                draw();
            }
        }

        function build(side, list, col) {
            list.forEach(function (it) {
                var dot = el('span', { class: 'match-dot' });
                var label = el('span', { class: 'match-label', text: it.text });
                var btn = el('button', { type: 'button', class: 'match-item exam-input', 'data-side': side, 'data-id': it.id },
                    side === 'l' ? [label, dot] : [dot, label]);
                items[side][it.id] = btn;
                btn.addEventListener('click', function () { if (!btn._dragged) { pick(side, it.id); } btn._dragged = false; });
                dot.addEventListener('pointerdown', function (e) { startDrag(e, side, it.id, btn); });
                col.appendChild(btn);
            });
        }

        // Drag from a dot, drop on an item of the other side.
        function startDrag(e, side, id, btn) {
            e.preventDefault();
            var temp = path('', 'match-line match-line-temp');
            svg.appendChild(temp);
            var from = center(dotOf(side, id));
            var moved = false;
            function move(ev) {
                moved = true;
                var b = board.getBoundingClientRect();
                var to = { x: ev.clientX - b.left, y: ev.clientY - b.top };
                temp.setAttribute('d', side === 'l' ? curve(from, to) : curve(to, from));
            }
            function up(ev) {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', up);
                document.removeEventListener('pointercancel', up);
                temp.remove();
                if (!moved) { return; }
                btn._dragged = true;
                setTimeout(function () { btn._dragged = false; }, 0);
                var target = document.elementFromPoint(ev.clientX, ev.clientY);
                var item = target && target.closest ? target.closest('.match-item') : null;
                if (item && board.contains(item) && item.dataset.side !== side) {
                    selected = null;
                    if (side === 'l') { connect(id, item.dataset.id); } else { connect(item.dataset.id, id); }
                }
            }
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
            document.addEventListener('pointercancel', up);
        }

        build('l', lefts, colL);
        build('r', rights, colR);
        board.appendChild(svg);
        board.appendChild(colL);
        board.appendChild(colR);

        // Lines depend on layout: redraw on resize and once the board is in the DOM.
        if (window.ResizeObserver) { new ResizeObserver(draw).observe(board); }
        window.addEventListener('resize', draw);
        setTimeout(draw, 0);

        return el('div', { class: 'match' }, [el('p', { class: 'empty', text: t('match_hint') }), board]);
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
        input.addEventListener('click', function () { suspendBlur(); });
        input.addEventListener('change', function () {
            var f = input.files[0];
            if (!f) { return; }
            if (f.size > MAX_FILE) {
                showDialog(t('file_too_large'), t('dialog_ok'), null);
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

    // Draw: opens a full-screen editor (Fabric.js, vendored in /assets/vendor)
    // with pen, eraser, text boxes, colours and widths. The editor content is
    // kept (as Fabric JSON) so the student can re-open and keep editing.
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

    var DRAW_COLORS = ['#111111', '#dc2626', '#2563eb', '#16a34a', '#f59e0b', '#9333ea'];
    var DRAW_WIDTHS = [2, 5, 10];

    function openDrawModal(q, onSave) {
        var fabric = window.fabric;
        var overlay = el('div', { class: 'modal-overlay' });
        var width = Math.min(1000, window.innerWidth - 24);
        var height = Math.max(240, Math.min(Math.round(width * 0.62), window.innerHeight - 170));

        var canvasEl = el('canvas');
        var holder = el('div', { class: 'modal-body' }, [canvasEl]);
        var canvas = new fabric.Canvas(canvasEl, { width: width, height: height, backgroundColor: '#ffffff', isDrawingMode: true, selection: true });

        var tool = 'pen';
        var color = DRAW_COLORS[0];
        var size = DRAW_WIDTHS[1];

        function applyBrush() {
            var brush = new fabric.PencilBrush(canvas);
            // The eraser paints white: the answer is exported on a white background.
            brush.color = tool === 'eraser' ? '#ffffff' : color;
            brush.width = tool === 'eraser' ? size * 3 : size;
            canvas.freeDrawingBrush = brush;
        }
        function applyToSelection() {
            var obj = canvas.getActiveObject();
            if (obj && obj.type === 'i-text') {
                obj.set({ fill: color, fontSize: 14 + size * 3 });
                canvas.requestRenderAll();
            }
        }

        var toolButtons = {};
        function setTool(name) {
            tool = name;
            canvas.isDrawingMode = name === 'pen' || name === 'eraser';
            canvas.selection = name === 'select';
            canvas.discardActiveObject();
            canvas.forEachObject(function (o) { o.selectable = name === 'select' || name === 'text'; o.evented = o.selectable; });
            canvas.defaultCursor = name === 'text' ? 'text' : 'default';
            if (canvas.isDrawingMode) { applyBrush(); }
            Object.keys(toolButtons).forEach(function (k) { toolButtons[k].classList.toggle('active', k === name); });
            canvas.requestRenderAll();
        }

        // Text boxes: click with the text tool to add one and start typing.
        canvas.on('mouse:down', function (opt) {
            if (tool !== 'text' || opt.target) { return; }
            var p = canvas.getPointer(opt.e);
            var box = new fabric.IText('', { left: p.x, top: p.y, fill: color, fontSize: 14 + size * 3, fontFamily: 'sans-serif' });
            canvas.add(box);
            canvas.setActiveObject(box);
            box.enterEditing();
            box.hiddenTextarea && box.hiddenTextarea.focus();
        });
        // Empty text boxes are dropped when editing ends.
        canvas.on('text:editing:exited', function (e) {
            if (e.target && !e.target.text.trim()) { canvas.remove(e.target); }
        });
        // Keep new objects non-selectable while drawing.
        canvas.on('path:created', function (e) { e.path.selectable = false; e.path.evented = false; });

        function btn(label, onClick, cls, title) {
            var b = el('button', { type: 'button', class: cls || 'button-secondary', text: label });
            if (title) { b.title = title; }
            b.addEventListener('click', onClick);
            return b;
        }

        var tools = el('div', { class: 'draw-group' });
        [['pen', 'draw_pen'], ['eraser', 'draw_eraser'], ['text', 'draw_text'], ['select', 'draw_select']].forEach(function (d) {
            toolButtons[d[0]] = btn(t(d[1]), function () { setTool(d[0]); }, 'button-secondary tool');
            tools.appendChild(toolButtons[d[0]]);
        });

        var swatches = el('div', { class: 'draw-group' });
        var swatchEls = DRAW_COLORS.map(function (c) {
            var sw = el('button', { type: 'button', class: 'swatch', 'aria-label': c, style: 'background:' + c });
            sw.addEventListener('click', function () {
                color = c;
                swatchEls.forEach(function (x) { x.classList.toggle('active', x === sw); });
                if (tool === 'eraser') { setTool('pen'); } else if (canvas.isDrawingMode) { applyBrush(); }
                applyToSelection();
            });
            swatches.appendChild(sw);
            return sw;
        });
        swatchEls[0].classList.add('active');

        var widths = el('div', { class: 'draw-group' });
        var widthEls = DRAW_WIDTHS.map(function (w) {
            var wb = el('button', { type: 'button', class: 'width-btn', 'aria-label': String(w) }, [el('span', { style: 'height:' + w + 'px' })]);
            wb.addEventListener('click', function () {
                size = w;
                widthEls.forEach(function (x) { x.classList.toggle('active', x === wb); });
                if (canvas.isDrawingMode) { applyBrush(); }
                applyToSelection();
            });
            widths.appendChild(wb);
            return wb;
        });
        widthEls[1].classList.add('active');

        var edit = el('div', { class: 'draw-group' }, [
            btn(t('draw_undo'), function () {
                var objs = canvas.getObjects();
                if (objs.length) { canvas.remove(objs[objs.length - 1]); }
            }),
            btn(t('draw_delete'), function () {
                var active = canvas.getActiveObjects();
                active.forEach(function (o) { canvas.remove(o); });
                canvas.discardActiveObject();
                canvas.requestRenderAll();
            }),
            btn(t('draw_clear'), function () {
                canvas.clear();
                canvas.backgroundColor = '#ffffff';
                canvas.requestRenderAll();
            })
        ]);

        function close() { canvas.dispose(); overlay.remove(); }
        var save = btn(t('draw_save'), function () {
            canvas.discardActiveObject();
            canvas.requestRenderAll();
            if (!canvas.getObjects().length) { close(); return; }
            state.drawData[q.id] = canvas.toJSON();
            // Export at 2x for readability on small screens.
            var dataUrl = canvas.toDataURL({ format: 'png', multiplier: 2 });
            fetch(dataUrl).then(function (r) { return r.blob(); }).then(function (blob) { onSave(blob); close(); });
        }, '');
        var cancel = btn(t('draw_cancel'), close);

        overlay.appendChild(el('div', { class: 'modal' }, [
            el('div', { class: 'draw-toolbar' }, [tools, swatches, widths, edit]),
            holder,
            el('div', { class: 'modal-actions' }, [cancel, save])
        ]));
        document.body.appendChild(overlay);

        if (state.drawData[q.id]) {
            canvas.loadFromJSON(state.drawData[q.id], function () {
                canvas.backgroundColor = '#ffffff';
                setTool('pen');
            });
        } else {
            setTool('pen');
        }
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
        Guard.init({ root: root, exam: state.exam, resumed: !!resumeInfo, lastSeen: resumeInfo ? resumeInfo.lastSeen : null });
        resumeInfo = null;
    }

    // ------------------------------------------------------------------
    // Start / resume
    // ------------------------------------------------------------------
    function postForm(url, fd) {
        return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) { return res.json().catch(function () { return { ok: false }; }); });
    }

    var resumeInfo = null;

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
        Guard.call('onUserStart'); // must run synchronously inside the click (fullscreen)
        var fd = new FormData(startForm);
        start(fd, null).catch(function () {
            startError.textContent = t('error_start');
            startError.hidden = false;
        });
    });

    var draft = loadDraft();
    if (draft && draft.sid) {
        resumeInfo = { lastSeen: draft.lastSeen || null };
        if (Array.isArray(draft.events)) { Guard.events = draft.events; }
        var fd = new FormData();
        fd.append('resume', draft.sid);
        start(fd, draft.answers || {}).catch(function () { clearDraft(); resumeInfo = null; Guard.events = []; });
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------
    function submitExam(button, errorBox) {
        if (state.submitting) { return; }
        var unanswered = state.exam.questions.filter(function (q) { return !isAnswered(q); }).length;
        showDialog(unanswered ? t('unanswered') : t('confirm_submit'), t('submit'), t('dialog_cancel')).then(function (yes) {
            if (yes) { doSubmit(button, errorBox); }
        });
    }

    function doSubmit(button, errorBox) {
        if (state.submitting) { return; }
        Guard.call('onBeforeSubmit');

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
            Guard.active = false;
            Guard.call('onFinish');
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

    // Heartbeat so a reopened exam can tell how long the browser was gone,
    // even if it was killed without any event.
    setInterval(function () { if (state.exam && !state.done) { writeDraft(); } }, 3000);

    window.addEventListener('beforeunload', function (e) {
        if (state.exam && !state.done) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
