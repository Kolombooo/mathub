document.addEventListener('DOMContentLoaded', function () {
    var i18nNode = document.getElementById('admin-i18n');
    var I18N = i18nNode ? (JSON.parse(i18nNode.textContent) || {}) : {};
    function t(key, fallback) { return I18N['admin_js.' + key] || fallback; }

    // Copy-to-clipboard for uploaded file URLs
    document.querySelectorAll('.copy-url').forEach(function (btn) {
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(btn.dataset.url).then(function () {
                var original = btn.textContent;
                btn.textContent = t('copied', 'Copied!');
                setTimeout(function () { btn.textContent = original; }, 1200);
            });
        });
    });

    // WYSIWYG markdown editor (Toast UI) on the material edit page
    var editorHost = document.getElementById('editor');
    if (!editorHost) {
        return;
    }

    var bodyField = document.getElementById('material-body');
    var form = document.getElementById('material-form');
    var titleField = document.getElementById('material-title');

    function uploadFile(file) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', window.MATHUB_CSRF);
        return fetch(window.MATHUB_UPLOAD_URL, { method: 'POST', body: formData })
            .then(function (res) { return res.json(); });
    }

    // Unsaved-changes guard and Ctrl/Cmd+S, shared by the visual and plain editors.
    var dirty = false;
    var saving = false;
    var initialTitle = titleField ? titleField.value : '';
    var initialBody = null;
    function markDirty() { dirty = true; }
    function currentBody() { return editor ? editor.getMarkdown() : bodyField.value; }
    function refreshDirty() {
        dirty = (titleField && titleField.value !== initialTitle) || (initialBody !== null && currentBody() !== initialBody);
    }
    if (titleField) { titleField.addEventListener('input', refreshDirty); }
    window.addEventListener('beforeunload', function (e) {
        if (dirty && !saving) { e.preventDefault(); e.returnValue = ''; }
    });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
        }
    });
    var editor = null;

    // The visual editor comes from a CDN. If that is blocked (school networks,
    // offline), fall back to the plain Markdown textarea instead of a blank page.
    if (typeof toastui === 'undefined') {
        bodyField.hidden = false;
        bodyField.classList.add('is-fallback');
        editorHost.hidden = true;
        var note = document.getElementById('editor-fallback-note');
        if (note) { note.hidden = false; }
        initialBody = bodyField.value;
        bodyField.addEventListener('input', refreshDirty);

        var plainInput = document.createElement('input');
        plainInput.type = 'file';
        plainInput.style.display = 'none';
        document.body.appendChild(plainInput);
        plainInput.addEventListener('change', function () {
            var file = plainInput.files[0];
            if (!file) return;
            uploadFile(file).then(function (data) {
                if (!data.ok) { alert(data.error || t('upload_failed', 'Upload failed')); return; }
                var isImage = /^image\//.test(file.type);
                var md = (isImage ? '!' : '') + '[' + (file.name || 'file') + '](' + data.url + ')';
                var s = bodyField.selectionStart, e2 = bodyField.selectionEnd;
                bodyField.value = bodyField.value.slice(0, s) + md + bodyField.value.slice(e2);
                bodyField.selectionStart = bodyField.selectionEnd = s + md.length;
                bodyField.focus();
                refreshDirty();
            }).catch(function () { alert(t('upload_failed', 'Upload failed')); });
            plainInput.value = '';
        });
        var plainAttach = document.getElementById('attach-file-btn');
        if (plainAttach) { plainAttach.addEventListener('click', function () { plainInput.click(); }); }
        form.addEventListener('submit', function () { saving = true; });
        return;
    }

    // Sized images are written as ![alt](url){width="300px"} (a CommonMark
    // attributes suffix the server-side renderer understands and applies to
    // the <img> tag). Toast UI's own markdown parser doesn't know that
    // syntax, so the marker shows up as plain trailing text next to the
    // image while editing; it never reaches the public page.
    editor = new toastui.Editor({
        el: editorHost,
        language: window.MATHUB_EDITOR_LANG || 'en-US',
        height: '100%',
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        initialValue: bodyField.value || '',
        hooks: {
            addImageBlobHook: function (blob, callback) {
                uploadFile(blob).then(function (data) {
                    if (data.ok) {
                        callback(data.url, blob.name || 'image');
                    } else {
                        alert(data.error || t('upload_failed', 'Upload failed'));
                    }
                }).catch(function () {
                    alert(t('upload_failed', 'Upload failed'));
                });
            }
        }
    });

    initialBody = editor.getMarkdown();
    editor.on('change', refreshDirty);

    setupImageResize(editor, editorHost);

    function setupImageResize(editor, editorHost) {
        var overlay = null;
        var activeImg = null;
        var dragging = false;
        var startX = 0;
        var startWidth = 0;

        function removeOverlay() {
            if (overlay) { overlay.remove(); overlay = null; }
            activeImg = null;
        }

        function positionOverlay() {
            if (!activeImg || !overlay) return;
            var r = activeImg.getBoundingClientRect();
            overlay.style.left = (r.right - 8) + 'px';
            overlay.style.top = (r.bottom - 8) + 'px';
        }

        function showOverlay(img) {
            removeOverlay();
            activeImg = img;
            overlay = document.createElement('div');
            overlay.className = 'mathub-resize-handle';
            document.body.appendChild(overlay);
            positionOverlay();
            overlay.addEventListener('mousedown', function (e) {
                dragging = true;
                startX = e.clientX;
                startWidth = activeImg.getBoundingClientRect().width;
                e.preventDefault();
            });
        }

        function applyWidth(img, width) {
            var src = img.getAttribute('src');
            var escapedSrc = src.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var re = new RegExp('(!\\[[^\\]]*\\]\\(' + escapedSrc + '\\))(\\{width="\\d+px"\\})?', 'g');
            var md = editor.getMarkdown();
            var replaced = false;
            md = md.replace(re, function (match, imgPart) {
                replaced = true;
                return imgPart + '{width="' + width + 'px"}';
            });
            if (replaced) {
                editor.setMarkdown(md);
            }
            removeOverlay();
        }

        document.addEventListener('mousemove', function (e) {
            if (!dragging || !activeImg) return;
            var newWidth = Math.max(40, Math.round(startWidth + (e.clientX - startX)));
            activeImg.style.width = newWidth + 'px';
            activeImg.style.maxWidth = 'none';
            positionOverlay();
        });

        document.addEventListener('mouseup', function () {
            if (dragging && activeImg) {
                applyWidth(activeImg, Math.round(activeImg.getBoundingClientRect().width));
            }
            dragging = false;
        });

        window.addEventListener('scroll', positionOverlay, true);
        window.addEventListener('resize', positionOverlay);

        editorHost.addEventListener('click', function (e) {
            var pane = e.target.closest('.toastui-editor-contents');
            if (e.target.tagName === 'IMG' && pane) {
                showOverlay(e.target);
            } else if (e.target !== overlay) {
                removeOverlay();
            }
        });
    }

    // Button to attach a PDF (or any other file) as a markdown link
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.style.display = 'none';
    document.body.appendChild(fileInput);
    fileInput.addEventListener('change', function () {
        var file = fileInput.files[0];
        if (!file) return;
        uploadFile(file).then(function (data) {
            if (data.ok) {
                editor.insertText('[' + file.name + '](' + data.url + ')');
            } else {
                alert(data.error || t('upload_failed', 'Upload failed'));
            }
        }).catch(function () {
            alert(t('upload_failed', 'Upload failed'));
        });
        fileInput.value = '';
    });

    var attachBtn = document.getElementById('attach-file-btn');
    if (attachBtn) {
        attachBtn.addEventListener('click', function () { fileInput.click(); });
    }

    form.addEventListener('submit', function () {
        saving = true;
        bodyField.value = editor.getMarkdown();
    });
});
