# Code editor bundle

`public/assets/vendor/codeeditor.min.js` is a one-file build of CodeMirror 6
(MIT, Marijn Haverbeke and contributors) with Python and JavaScript support.
It exposes `window.MathubCode.create(parent, {doc, language, onChange, label})`.

Rebuild (only needed to change the editor features):

```bash
cd tools/codeeditor
npm init -y && npm i esbuild codemirror @codemirror/lang-python @codemirror/lang-javascript \
  @codemirror/commands @codemirror/language @codemirror/state @codemirror/view \
  @codemirror/autocomplete @codemirror/search
npx esbuild entry.js --bundle --minify --format=iife --target=es2019 \
  --outfile=../../public/assets/vendor/codeeditor.min.js
```
