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
