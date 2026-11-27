# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
A single teacher who administers the site, and their students. Students read class materials and take exams, supervised in class or on school or personal devices. Students have no accounts: they enter an exam code, then their name and surname. No other audiences (parents, other teachers) were confirmed.

## Product Purpose
MatHub is a self-hosted material hub where one teacher publishes class materials (Markdown pages with images and PDFs) organized as Classes → Topics → Materials, and runs online exams tied to specific lessons. Success: the teacher publishes and reorders materials with little effort, and students find them and sit exams without friction.

## Positioning
Simple and self-hosted: one teacher owns everything. Content is plain files under `content/` and `data/`, backed up by copying directories, with no student accounts and no vendor. A hosted LMS (Google Classroom, Moodle) cannot truthfully claim this.

## Operating Context
Teacher works in an admin panel (`/admin`) with a WYSIWYG Markdown editor, drag-and-drop image upload and a reusable exam content library. Students use the public site: browse classes, topics and materials, or type an exam code on the home page. An exam is one page; answers stay in the browser and are submitted in a single request at the end.

## Capabilities and Constraints
- PHP 8.0+, Composer (`league/commonmark`), file-based storage, Apache/Docker deployment.
- UI is bilingual, Czech and English (`lang/cs.json`, `lang/en.json`); all copy must exist in both.
- Exam question types: radio, checkbox, select, short text, number, long text, connect A to B, draw (Fabric.js). Two more exam kinds: a single long-form *essay* page (formatting toolbar, word/letter count, paste limited to text copied from the site plus pictures) and *code* assignments.
- Optional correct answers; partial credit on matching; other types scored by hand.
- Optional anti-cheating monitoring (page-leave, fullscreen, close/reload, heartbeat), with students told when it is on; flagged events shown to the teacher.
- Optional per-exam time limit, enforced server-side.
- **Students never see scores.** Scoring, comments and monitoring evidence are teacher-only.

## Brand Commitments
None binding. The current visual look (blue tokens, Hanken Grotesk/Figtree, "Mensa IS Library" Figma reference in `public/assets/style.css`) is the incumbent implementation, not a confirmed brand constraint.

## Evidence on Hand
Working application in this repository. No testimonials, customer names, usage numbers or published brand assets were provided; future work must not fabricate them.

## Product Principles
1. Simplicity for the sole teacher: every feature must be operable by one person without training or support.
2. Students never need an account; entering is as light as typing a code.
3. The teacher owns their data as plain files; nothing locks content in.
4. Exams are trustworthy but honest: monitoring is disclosed to students, and evidence is shown only to the teacher.
5. Czech and English are equal citizens.
