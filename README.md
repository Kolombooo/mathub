# MatHub

A self-hosted material hub for a single teacher to publish class materials
(Markdown pages with images and PDFs) organized as **Classes → Topics →
Materials**.

## Requirements

- PHP 8.0+
- Composer (for the `league/commonmark` dependency)

## Setup

```bash
composer install
```

Point your webserver's document root at the `public/` directory. `src/`,
`content/` and `data/` must stay outside the public webroot (or be denied by
the included `.htaccess` files if the whole project sits under one vhost).

### Quick local run

```bash
php -S localhost:8000 -t public public/router.php
```

Then open http://localhost:8000.

## Admin panel

Visit `/admin/login`. A default account is created automatically on first
run:

- username: `teacher`
- password: `changeme`

**Change the password immediately** via `/admin/account` after your first
login.

From the admin panel you can:

- Create/rename/delete classes and topics
- Write materials with a WYSIWYG Markdown editor (drag-and-drop image
  upload, and a button to attach PDFs/other files as links)
- Reorder materials within a topic
- Manage uploaded files per topic

## Exams

Each class has an **Exams** tab (**Testy** in Czech) on its public page.
Students have no accounts: they enter the class code, pick an exam and type
their name and surname.

Teachers (admin → class → *Exams*):

- Set the class code (empty = exams locked) and the class language
  (controls the tab names and the student UI language)
- Build exams with these question types: radio, checkbox, select, short
  text, number, long text, connect A to B (shuffled per student), file
  upload, draw (Signature Pad, vendored in `public/assets/vendor`)
- Optional correct answers (several accepted); matching gives partial credit.
  Long text, upload, draw and questions without a correct answer are scored
  by hand
- See who is still working and who submitted (with time taken), open a
  submission, edit scores, comment and mark it as *Marked*. Students never see
  scores.

The exam is one page: loaded once, answers kept in the browser (plus a
localStorage draft for refresh recovery) and sent in a single request at the
end. Exam data lives in `data/exams/<class>/<exam>/` (not in `content/`, so it
is not mistaken for topics).

Anti-cheating is prepared but not implemented: see the `MathubExamGuard`
comment at the top of `public/assets/exam.js`, the reserved per-exam
`settings`, and the stored `events` list shown on the submission page.

## Data layout

Everything is stored as plain files under `content/`:

```
content/
  <class-slug>/
    meta.json
    <topic-slug>/
      meta.json          (title + material order)
      <material-slug>.md
      files/             (uploaded images & PDFs)
```

This makes the whole site backup-able by copying the `content/` and `data/`
directories.
