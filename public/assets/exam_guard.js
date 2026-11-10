/*
 * Anti-cheating guards for exams (see the MathubExamGuard docs in exam.js).
 *
 *  1. visibility   Page Visibility API (+ window blur): switching tab, opening
 *                  a new tab/window, minimising, alt-tab. Logs `left_page`
 *                  once the student is back, with when they left and for how
 *                  long.
 *  2. fullscreen   The exam starts in fullscreen. Leaving it logs
 *                  `fullscreen_exit` (when + how long) and blocks the exam
 *                  with an overlay until the student returns. Fullscreen is
 *                  released after submitting.
 *  3. lifecycle    Closing/reloading the page sends `page_closed` to the
 *                  server immediately (sendBeacon, server timestamp). When
 *                  the exam is reopened, `page_reopened` is logged with how
 *                  long the browser was gone (from a localStorage heartbeat,
 *                  so it also works if the browser was killed).
 *
 * Not implemented on purpose: developer-tools detection. There is no
 * reliable browser API for it (see the PR / discussion).
 *
 * All durations are seconds; `at` is when the episode STARTED.
 */
(function () {
    'use strict';
    var Guard = window.MathubExamGuard;
    if (!Guard) { return; }

    function secs(ms) { return Math.max(0, Math.round(ms / 1000)); }
    function monitoring() { return Guard.settings.monitor !== false; }

    // ---------------------------------------------------------------- visibility
    Guard.register((function () {
        var ctx = null;
        var away = null; // { start, reason }

        function isAway() {
            if (document.hidden) { return 'hidden'; }
            if (!Guard.blurSuspended && !document.hasFocus()) { return 'blur'; }
            return null;
        }
        function check() {
            if (!ctx || !Guard.active) { return; }
            var reason = isAway();
            if (reason && !away) {
                away = { start: Date.now(), reason: reason };
            } else if (!reason && away) {
                ctx.log('left_page', { reason: away.reason, durationSec: secs(Date.now() - away.start) }, away.start);
                away = null;
            } else if (reason === 'hidden' && away && away.reason === 'blur') {
                away.reason = 'hidden'; // upgrade: the page really went to the background
            }
        }
        function flush() {
            if (away && ctx) {
                ctx.log('left_page', { reason: away.reason, durationSec: secs(Date.now() - away.start) }, away.start);
                away = null;
            }
        }

        return {
            name: 'visibility',
            init: function (c) {
                if (!monitoring()) { return; }
                ctx = c;
                document.addEventListener('visibilitychange', check);
                window.addEventListener('blur', check);
                window.addEventListener('focus', check);
                window.addEventListener('mathub-guard-recheck', check);
                check();
            },
            onBeforeSubmit: flush,
            onFinish: function () {
                flush();
                document.removeEventListener('visibilitychange', check);
                window.removeEventListener('blur', check);
                window.removeEventListener('focus', check);
                window.removeEventListener('mathub-guard-recheck', check);
                ctx = null;
            }
        };
    })());

    // ---------------------------------------------------------------- fullscreen
    Guard.register((function () {
        var ctx = null;
        var overlay = null;
        var exitedAt = null;
        var finished = false;

        function supported() { return !!(document.documentElement.requestFullscreen && document.fullscreenEnabled); }
        function wanted() { return Guard.settings.fullscreen !== false && supported(); }
        function enter() {
            var p = document.documentElement.requestFullscreen();
            if (p && p.catch) { p.catch(function () { /* user must click the overlay button */ }); }
        }

        function showOverlay() {
            if (overlay || !ctx) { return; }
            overlay = document.createElement('div');
            overlay.className = 'fs-overlay';
            var box = document.createElement('div');
            box.className = 'fs-box';
            var h = document.createElement('h2'); h.textContent = ctx.t('fs_title');
            var p = document.createElement('p'); p.textContent = ctx.t('fs_text');
            var b = document.createElement('button'); b.type = 'button'; b.textContent = ctx.t('fs_button');
            b.addEventListener('click', enter);
            box.appendChild(h); box.appendChild(p); box.appendChild(b);
            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }
        function hideOverlay() {
            if (overlay) { overlay.remove(); overlay = null; }
        }

        function onChange() {
            if (finished || !ctx) { return; }
            if (document.fullscreenElement) {
                if (exitedAt !== null) {
                    if (monitoring()) { ctx.log('fullscreen_exit', { durationSec: secs(Date.now() - exitedAt) }, exitedAt); }
                    exitedAt = null;
                }
                hideOverlay();
            } else {
                if (exitedAt === null) { exitedAt = Date.now(); }
                showOverlay();
            }
        }

        return {
            name: 'fullscreen',
            onUserStart: function () {
                if (wanted()) { enter(); }
            },
            init: function (c) {
                ctx = c;
                finished = false;
                if (Guard.settings.fullscreen === false) { return; }
                if (!supported()) {
                    // e.g. iPhone Safari. Informational only (not flagged red).
                    if (monitoring()) { ctx.log('fullscreen_unsupported', null); }
                    return;
                }
                document.addEventListener('fullscreenchange', onChange);
                // Resumed after a reload (no user gesture) or the request was refused:
                // ask the student to click. This is not an "exit", nothing is logged.
                if (!document.fullscreenElement) { showOverlay(); }
            },
            onBeforeSubmit: function () {
                if (exitedAt !== null && ctx && monitoring()) {
                    ctx.log('fullscreen_exit', { durationSec: secs(Date.now() - exitedAt) }, exitedAt);
                    exitedAt = null;
                }
            },
            onFinish: function () {
                finished = true;
                document.removeEventListener('fullscreenchange', onChange);
                hideOverlay();
                if (document.fullscreenElement && document.exitFullscreen) {
                    document.exitFullscreen().catch(function () { /* already out */ });
                }
                ctx = null;
            }
        };
    })());

    // ---------------------------------------------------------------- lifecycle
    Guard.register((function () {
        var ctx = null;
        function onPageHide() {
            if (ctx && Guard.active) { ctx.beacon('page_closed', { reason: 'pagehide' }); }
        }
        return {
            name: 'lifecycle',
            init: function (c) {
                if (!monitoring()) { return; }
                ctx = c;
                if (c.resumed && c.lastSeen) {
                    c.log('page_reopened', { durationSec: secs(Date.now() - c.lastSeen) }, c.lastSeen);
                }
                window.addEventListener('pagehide', onPageHide);
            },
            onFinish: function () {
                window.removeEventListener('pagehide', onPageHide);
                ctx = null;
            }
        };
    })());
})();
