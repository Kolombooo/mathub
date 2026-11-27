/*
 * Code exams (student side). Loaded by exam.php for exams whose content kind is
 * "code"; exam.js hands the whole exam layout over to MathubExamCode.render().
 *
 * Layout: a sidebar lists the assignments; the main pane shows the selected one
 * with two tabs, "Zadání" (Markdown written by the teacher, rendered by the
 * server) and "Kód" (a CodeMirror editor, Run/Stop, stdin and an output panel).
 *
 * Running code: entirely in the student's browser, inside a sandboxed iframe
 * (code_runner.html) that hosts Web Workers: JavaScript natively, Python via
 * Pyodide (WebAssembly, loaded from a CDN on first use).
 *
 * Saving: every change goes to state.answers (-> localStorage draft, via
 * exam.js) and, a few seconds later, to the server (autosave). The server writes
 * one small file per student, so many students typing at once do not contend.
 * The final answers are still sent with the normal single submit.
 *
 * Chatbot (NOT implemented): each assignment has a `chat` flag (set by the
 * teacher, stored with the content) and the Code tab contains an empty, hidden
 * <aside data-chat-slot>. A future module sets `MathubExamCode.chat =
 * { mount(question, container, api) }`; it is called once per assignment pane.
 * Transcripts belong in submission.chats[questionId] (reserved server-side).
 */
(function () {
    'use strict';

    var M = window.MathubExamCode = { chat: null };

    // Autosave pacing: first change schedules a save after SAVE_DELAY (+ jitter, so 50
    // students do not hit the server in lockstep); more typing in between rides along.
    var SAVE_DELAY = 6000;
    var SAVE_JITTER = 2500;
    var RETRY_BASE = 3000;
    var RETRY_MAX = 30000;

    M.render = function (api) {
        var el = api.el, t = api.t, state = api.state, app = api.app;
        var questions = state.exam.questions;
        var pyBase = app.dataset.pyodideBase || '';

        document.body.classList.add('page-exam-code');
        state.codeAt = state.codeAt || {};

        // ------------------------------------------------------------------
        // Runner (sandboxed iframe), created on first use
        // ------------------------------------------------------------------
        var frame = null, frameReady = false, queue = [], seq = 0, listeners = {};

        function ensureFrame() {
            if (frame) { return; }
            frame = el('iframe', { sandbox: 'allow-scripts', title: 'runner', 'aria-hidden': 'true', tabindex: '-1', class: 'code-runner-frame' });
            frame.src = app.dataset.runnerUrl;
            document.body.appendChild(frame);
            window.addEventListener('message', function (e) {
                if (!frame || e.source !== frame.contentWindow) { return; }
                var m = e.data || {};
                if (m.t === 'ready') { frameReady = true; queue.splice(0).forEach(sendToFrame); return; }
                var l = listeners[m.id];
                if (l) { l(m); }
            });
        }
        function sendToFrame(m) { frame.contentWindow.postMessage(m, '*'); }
        function post(m) { ensureFrame(); if (frameReady) { sendToFrame(m); } else { queue.push(m); } }

        // Start fetching Pyodide in the background once the exam is on screen, so the first Run is quick.
        if (questions.some(function (q) { return q.language === 'python'; })) {
            setTimeout(function () { if (!state.done) { post({ t: 'preload', language: 'python', base: pyBase }); } }, 1500);
        }

        var running = null; // { qid, id }

        // ------------------------------------------------------------------
        // Server autosave
        // ------------------------------------------------------------------
        var dirty = {}, inflight = false, saveTimer = null, fails = 0;
        var statusBox = el('div', { class: 'code-save', role: 'status', 'aria-live': 'polite' });

        function setStatus(kind, at) {
            var text = '';
            if (kind === 'saved') {
                var d = new Date((at || Math.floor(Date.now() / 1000)) * 1000);
                text = t('code_saved') + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            } else if (kind === 'saving') { text = t('code_saving'); }
            else if (kind === 'pending') { text = t('code_unsaved'); }
            else if (kind === 'error') { text = t('code_save_failed'); }
            statusBox.textContent = text;
            statusBox.classList.toggle('code-save-error', kind === 'error');
        }
        function hasDirty() { return Object.keys(dirty).length > 0; }

        function schedule(delay) {
            if (saveTimer) { return; }
            saveTimer = setTimeout(function () { saveTimer = null; flush(false); }, delay);
        }

        function flush(unloading) {
            if (inflight || state.done || !state.sid || !hasDirty()) { return; }
            var ids = Object.keys(dirty), payload = {};
            ids.forEach(function (id) { payload[id] = typeof state.answers[id] === 'string' ? state.answers[id] : ''; });
            var fd = new FormData();
            fd.append('csrf_token', app.dataset.csrf);
            fd.append('payload', JSON.stringify(payload));
            inflight = true;
            setStatus('saving');
            var done = function (data) {
                inflight = false;
                if (data && data.ok) {
                    fails = 0;
                    ids.forEach(function (id) { if (state.answers[id] === payload[id] || (state.answers[id] === undefined && payload[id] === '')) { delete dirty[id]; } });
                    if (hasDirty()) { setStatus('pending'); schedule(SAVE_DELAY / 2); } else { setStatus('saved', data.at); }
                } else {
                    fails++;
                    setStatus('error');
                    // 429 "retry" (too fast) or a network/server problem: back off with jitter.
                    schedule(Math.min(RETRY_MAX, RETRY_BASE * Math.pow(2, Math.min(fails, 4))) + Math.random() * 1500);
                }
            };
            try {
                fetch(app.dataset.saveUrl + encodeURIComponent(state.sid), { method: 'POST', body: fd, credentials: 'same-origin', keepalive: !!unloading })
                    .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
                    .then(done, function () { done(null); });
            } catch (e) { done(null); }
        }

        function markDirty(qid) {
            dirty[qid] = true;
            state.codeAt[qid] = Date.now();
            setStatus('pending');
            schedule(SAVE_DELAY + Math.random() * SAVE_JITTER);
        }

        document.addEventListener('visibilitychange', function () { if (document.hidden) { flush(true); } });
        window.addEventListener('pagehide', function () { flush(true); });

        // ------------------------------------------------------------------
        // Layout
        // ------------------------------------------------------------------
        var root = el('div', { class: 'code-exam' });
        var side = el('aside', { class: 'code-side', 'aria-label': t('code_assignments') });
        var list = el('ul', { class: 'code-list' });
        var main = el('div', { class: 'code-main' });
        side.appendChild(el('h2', { class: 'code-side-title', text: t('code_assignments') }));
        side.appendChild(list);
        var foot = el('div', { class: 'code-side-foot' }, [statusBox, api.errorBox, api.submitButton]);
        side.appendChild(foot);
        root.appendChild(side);
        root.appendChild(main);

        var items = {}, panes = {}, currentId = null;

        function codeOf(q) { return typeof state.answers[q.id] === 'string' ? state.answers[q.id] : (q.starter || ''); }
        function isEdited(q) {
            var a = state.answers[q.id];
            return typeof a === 'string' && a.trim() !== '' && a !== (q.starter || '');
        }
        function refreshItem(q) {
            items[q.id].classList.toggle('is-edited', isEdited(q));
            items[q.id].querySelector('.code-item-mark').textContent = isEdited(q) ? '●' : '';
        }

        questions.forEach(function (q, i) {
            var btn = el('button', { type: 'button', class: 'code-item', 'data-qid': q.id }, [
                el('span', { class: 'code-item-num', text: String(i + 1) }),
                el('span', { class: 'code-item-title', text: q.text || (t('code_assignment') + ' ' + (i + 1)) }),
                el('span', { class: 'code-item-mark', 'aria-label': t('code_edited') })
            ]);
            btn.addEventListener('click', function () { select(q.id); });
            items[q.id] = btn;
            list.appendChild(el('li', {}, [btn]));
        });

        function select(qid) {
            currentId = qid;
            questions.forEach(function (q) {
                var on = q.id === qid;
                items[q.id].classList.toggle('is-active', on);
                if (on) { items[q.id].setAttribute('aria-current', 'true'); } else { items[q.id].removeAttribute('aria-current'); }
                if (panes[q.id]) { panes[q.id].root.hidden = !on; }
            });
            var q = questions.filter(function (x) { return x.id === qid; })[0];
            if (!panes[qid]) { panes[qid] = buildPane(q, questions.indexOf(q)); main.appendChild(panes[qid].root); }
            panes[qid].root.hidden = false;
            if (panes[qid].onShow) { panes[qid].onShow(); }
            try { sessionStorage.setItem('mathub-code-' + state.sid, qid); } catch (e) { /* ignore */ }
        }

        // ------------------------------------------------------------------
        // One assignment: tabs Zadání / Kód
        // ------------------------------------------------------------------
        function buildPane(q, index) {
            var paneRoot = el('section', { class: 'code-pane', 'data-question-id': q.id, 'data-question-type': 'code' });
            var langName = q.language === 'python' ? 'Python' : 'JavaScript';
            paneRoot.appendChild(el('header', { class: 'code-head' }, [
                el('h2', { text: (index + 1) + '. ' + (q.text || t('code_assignment')) }),
                el('span', { class: 'badge badge-blue', text: langName }),
                el('span', { class: 'q-points', text: api.fmtPoints(q.points) })
            ]));

            var tabs = el('div', { class: 'code-tabs', role: 'tablist' });
            var tabTask = el('button', { type: 'button', role: 'tab', class: 'code-tab', id: 'tab-task-' + q.id, 'aria-controls': 'panel-task-' + q.id, text: t('code_tab_task') });
            var tabCode = el('button', { type: 'button', role: 'tab', class: 'code-tab', id: 'tab-code-' + q.id, 'aria-controls': 'panel-code-' + q.id, text: t('code_tab_code') });
            tabs.appendChild(tabTask);
            tabs.appendChild(tabCode);
            paneRoot.appendChild(tabs);

            // --- Zadání: server-rendered Markdown
            var task = el('div', { class: 'code-panel material code-task', role: 'tabpanel', id: 'panel-task-' + q.id, 'aria-labelledby': tabTask.id });
            task.innerHTML = q.descriptionHtml || '';
            if (!task.textContent.trim()) { task.appendChild(el('p', { class: 'muted', text: t('code_no_description') })); }
            task.querySelectorAll('a').forEach(function (a) { a.target = '_blank'; a.rel = 'noopener noreferrer'; });

            // --- Kód: editor + run + output
            var code = el('div', { class: 'code-panel code-code', role: 'tabpanel', id: 'panel-code-' + q.id, 'aria-labelledby': tabCode.id });
            var runBtn = el('button', { type: 'button', class: 'code-run', text: '▶ ' + t('code_run') });
            var stopBtn = el('button', { type: 'button', class: 'button-secondary', text: t('code_stop'), hidden: 'hidden' });
            var resetBtn = el('button', { type: 'button', class: 'button-secondary', text: t('code_reset') });
            var runInfo = el('span', { class: 'code-run-info', role: 'status', 'aria-live': 'polite' });
            code.appendChild(el('div', { class: 'code-toolbar' }, [runBtn, stopBtn, resetBtn, el('span', { class: 'toolbar-spacer' }), runInfo]));

            var holder = el('div', { class: 'code-editor' });
            code.appendChild(holder);

            var stdin = el('textarea', { class: 'mono code-stdin', rows: '3', placeholder: t('code_stdin_ph'), 'aria-label': t('code_stdin') });
            var stdinBox = el('details', { class: 'code-stdin-box' }, [el('summary', { text: t('code_stdin') }), stdin]);
            code.appendChild(stdinBox);

            var output = el('pre', { class: 'code-output', 'aria-label': t('code_output'), tabindex: '0' });
            var outHead = el('div', { class: 'code-output-head' }, [el('strong', { text: t('code_output') })]);
            var clearBtn = el('button', { type: 'button', class: 'button-secondary small', text: t('code_clear') });
            outHead.appendChild(clearBtn);
            code.appendChild(outHead);
            code.appendChild(output);

            // Reserved for the guarded chatbot (not implemented).
            var chatSlot = el('aside', { class: 'code-chat', 'data-chat-slot': q.id, hidden: 'hidden' });
            code.appendChild(chatSlot);

            paneRoot.appendChild(task);
            paneRoot.appendChild(code);

            // --- tabs behaviour
            function showTab(which) {
                var isTask = which === 'task';
                tabTask.setAttribute('aria-selected', String(isTask));
                tabCode.setAttribute('aria-selected', String(!isTask));
                tabTask.tabIndex = isTask ? 0 : -1;
                tabCode.tabIndex = isTask ? -1 : 0;
                tabTask.classList.toggle('is-active', isTask);
                tabCode.classList.toggle('is-active', !isTask);
                task.hidden = !isTask;
                code.hidden = isTask;
                if (!isTask && editor) { editor.view.requestMeasure(); }
            }
            tabTask.addEventListener('click', function () { showTab('task'); });
            tabCode.addEventListener('click', function () { showTab('code'); if (editor) { editor.focus(); } });
            tabs.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    var goCode = tabTask.getAttribute('aria-selected') === 'true';
                    showTab(goCode ? 'code' : 'task');
                    (goCode ? tabCode : tabTask).focus();
                    e.preventDefault();
                }
            });

            // --- editor
            var editor = null;
            if (window.MathubCode) {
                editor = window.MathubCode.create(holder, {
                    doc: codeOf(q), language: q.language, label: t('code_editor_label'),
                    onChange: function (v) { onCode(q, v); }
                });
            } else {
                var ta = el('textarea', { class: 'mono code-fallback', rows: '16', spellcheck: 'false' });
                ta.value = codeOf(q);
                ta.addEventListener('input', function () { onCode(q, ta.value); });
                holder.appendChild(ta);
                editor = { view: { requestMeasure: function () {} }, getValue: function () { return ta.value; }, setValue: function (v) { ta.value = v; onCode(q, v); }, focus: function () { ta.focus(); } };
            }
            holder.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runBtn.click(); }
            });

            // --- output helpers
            var outChars = 0, OUT_MAX = 100000;
            function addText(stream, text) {
                if (outChars > OUT_MAX) { return; }
                outChars += text.length;
                var span = el('span', { class: 'out-' + stream });
                span.textContent = text;
                output.appendChild(span);
                output.scrollTop = output.scrollHeight;
            }
            function clearOutput() { output.innerHTML = ''; outChars = 0; }
            clearBtn.addEventListener('click', clearOutput);

            function setRunning(on) {
                runBtn.disabled = on;
                stopBtn.hidden = !on;
                if (!on) { runInfo.textContent = ''; }
            }

            var REASONS = { timeout: 'code_timeout', stopped: 'code_stopped', output: 'code_output_limit', crash: 'code_crashed' };

            runBtn.addEventListener('click', function () {
                if (running) { runInfo.textContent = t('code_busy'); return; }
                clearOutput();
                // The program reads input but nothing was provided: show the box instead of failing silently.
                if (!stdin.value && /\b(input|prompt|readline)\s*\(/.test(editor.getValue())) { stdinBox.open = true; }
                var id = ++seq;
                running = { qid: q.id, id: id };
                setRunning(true);
                runInfo.textContent = q.language === 'python' ? t('code_loading') : t('code_running');
                var t0 = Date.now();
                listeners[id] = function (m) {
                    if (m.t === 'out') { addText(m.s, m.text); }
                    else if (m.t === 'img') {
                        var img = el('img', { class: 'code-figure', alt: t('code_figure'), src: m.url });
                        output.appendChild(img);
                        output.scrollTop = output.scrollHeight;
                    } else if (m.t === 'status') {
                        if (m.text === 'loading') { runInfo.textContent = t('code_loading'); }
                        else if (m.text === 'packages') { runInfo.textContent = t('code_packages'); }
                        else if (m.text === 'running') { runInfo.textContent = t('code_running'); }
                        else if (m.text.indexOf('pkg:') === 0) { runInfo.textContent = m.text.slice(4).slice(0, 80); }
                    } else if (m.t === 'done') {
                        delete listeners[id];
                        running = null;
                        setRunning(false);
                        if (m.reason && REASONS[m.reason]) { addText('info', '\n' + t(REASONS[m.reason]) + '\n'); }
                        else if (m.ok) { addText('info', (output.textContent ? '\n' : '') + t('code_finished').replace('{s}', ((Date.now() - t0) / 1000).toFixed(1)) + '\n'); }
                    }
                };
                post({ t: 'run', id: id, language: q.language, code: editor.getValue(), stdin: stdin.value, base: pyBase });
            });
            stopBtn.addEventListener('click', function () { post({ t: 'stop' }); });

            resetBtn.addEventListener('click', function () {
                api.showDialog(t('code_reset_confirm'), t('code_reset'), t('dialog_cancel')).then(function (yes) {
                    if (yes) { editor.setValue(q.starter || ''); editor.focus(); }
                });
            });

            if (M.chat && q.chat && typeof M.chat.mount === 'function') {
                try { chatSlot.hidden = false; M.chat.mount(q, chatSlot, api); } catch (e) { chatSlot.hidden = true; }
            }

            showTab('task');
            return { root: paneRoot, onShow: function () { if (editor) { editor.view.requestMeasure(); } } };
        }

        function onCode(q, value) {
            api.setAnswer(q.id, value);
            markDirty(q.id);
            refreshItem(q);
        }

        // Stop a run that outlives its page state (submitted / time up).
        window.addEventListener('mathub-exam-done', function () { if (frame) { post({ t: 'stop' }); } });

        questions.forEach(refreshItem);
        var first = questions.length ? questions[0].id : null;
        try {
            var remembered = sessionStorage.getItem('mathub-code-' + state.sid);
            if (remembered && items[remembered]) { first = remembered; }
        } catch (e) { /* ignore */ }
        if (first) { select(first); }
        // Work restored from this browser that is newer than the server's copy goes up again.
        (state.resumeDirty || []).forEach(function (qid) { dirty[qid] = true; });
        state.resumeDirty = [];
        if (hasDirty()) { setStatus('pending'); schedule(2000 + Math.random() * 1500); }

        return root;
    };
})();
