import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter, drawSelection, dropCursor, rectangularSelection, highlightSpecialChars } from '@codemirror/view';
import { EditorState, Compartment } from '@codemirror/state';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { indentOnInput, bracketMatching, syntaxHighlighting, defaultHighlightStyle, indentUnit, foldGutter, foldKeymap } from '@codemirror/language';
import { closeBrackets, closeBracketsKeymap, autocompletion, completionKeymap } from '@codemirror/autocomplete';
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search';
import { python } from '@codemirror/lang-python';
import { javascript } from '@codemirror/lang-javascript';

function create(parent, opts) {
    var lang = opts.language === 'python' ? python() : javascript();
    var onChange = opts.onChange || function () {};
    var view = new EditorView({
        parent: parent,
        state: EditorState.create({
            doc: opts.doc || '',
            extensions: [
                lineNumbers(), highlightActiveLineGutter(), highlightSpecialChars(), history(), foldGutter(),
                drawSelection(), dropCursor(), EditorState.allowMultipleSelections.of(true),
                indentOnInput(), bracketMatching(), closeBrackets(), autocompletion(),
                rectangularSelection(), highlightActiveLine(), highlightSelectionMatches(),
                syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                indentUnit.of(opts.language === 'python' ? '    ' : '  '),
                EditorState.tabSize.of(opts.language === 'python' ? 4 : 2),
                keymap.of([...closeBracketsKeymap, ...defaultKeymap, ...searchKeymap, ...historyKeymap, ...foldKeymap, ...completionKeymap, indentWithTab]),
                lang,
                EditorView.updateListener.of(function (u) { if (u.docChanged) { onChange(u.state.doc.toString()); } }),
                EditorView.contentAttributes.of({ 'aria-label': opts.label || 'Code editor', spellcheck: 'false', autocapitalize: 'off', autocorrect: 'off' })
            ]
        })
    });
    return {
        view: view,
        getValue: function () { return view.state.doc.toString(); },
        setValue: function (v) { view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: v } }); },
        focus: function () { view.focus(); },
        destroy: function () { view.destroy(); }
    };
}
window.MathubCode = { create: create };
