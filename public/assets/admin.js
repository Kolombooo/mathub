document.addEventListener('DOMContentLoaded', function () {
    // Copy-to-clipboard for uploaded file URLs
    document.querySelectorAll('.copy-url').forEach(function (btn) {
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(btn.dataset.url).then(function () {
                var original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = original; }, 1200);
            });
        });
    });

    // WYSIWYG markdown editor (Toast UI) on the material edit page
    var editorHost = document.getElementById('editor');
    if (!editorHost || typeof toastui === 'undefined') {
        return;
    }

    var bodyField = document.getElementById('material-body');

    function uploadFile(file) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', window.MATHUB_CSRF);
        return fetch(window.MATHUB_UPLOAD_URL, { method: 'POST', body: formData })
            .then(function (res) { return res.json(); });
    }

    // Sized images are written as ![alt](url){width="300px"} (a CommonMark
    // attributes suffix the server-side renderer understands and applies to
    // the <img> tag). Toast UI's own markdown parser doesn't know that
    // syntax, so the marker shows up as plain trailing text next to the
    // image while editing; it never reaches the public page.
    var editor = new toastui.Editor({
        el: editorHost,
        height: '500px',
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        initialValue: bodyField.value || '',
        hooks: {
            addImageBlobHook: function (blob, callback) {
                uploadFile(blob).then(function (data) {
                    if (data.ok) {
                        callback(data.url, blob.name || 'image');
                    } else {
                        alert(data.error || 'Upload failed');
                    }
                }).catch(function () {
                    alert('Upload failed');
                });
            }
        }
    });

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
                alert(data.error || 'Upload failed');
            }
        }).catch(function () {
            alert('Upload failed');
        });
        fileInput.value = '';
    });

    var attachBtn = document.getElementById('attach-file-btn');
    if (attachBtn) {
        attachBtn.addEventListener('click', function () { fileInput.click(); });
    }

    var form = document.getElementById('material-form');
    form.addEventListener('submit', function () {
        bodyField.value = editor.getMarkdown();
    });
});
