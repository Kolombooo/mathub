---
name: MatHub
description: A calm, plain teacher's workspace and student site for class materials and exams.
colors:
  blue: "#46689f"
  blue-hover: "#3c5b90"
  blue-subtle: "#e3e8f1"
  green: "#00a174"
  green-text: "#007857"
  green-subtle: "#dbf2ec"
  orange: "#f18816"
  orange-text: "#9c5409"
  orange-subtle: "#fdeede"
  red: "#d4605f"
  red-text: "#b03a39"
  red-subtle: "#fbe2e2"
  ink: "#1f2733"
  ink-secondary: "#4a5666"
  placeholder: "#8792a3"
  gray-text: "#606d81"
  gray-subtle: "#eff0f3"
  surface: "#ffffff"
  surface-muted: "#f6f8fb"
  border: "#e2e7ee"
  input-border: "#d3dae4"
  input-border-hover: "#b3bfce"
typography:
  display:
    fontFamily: "Hanken Grotesk, Figtree, system-ui, sans-serif"
    fontSize: "36px"
    fontWeight: 700
    lineHeight: 1.25
  headline:
    fontFamily: "Hanken Grotesk, Figtree, system-ui, sans-serif"
    fontSize: "24px"
    fontWeight: 600
    lineHeight: 1.25
  title:
    fontFamily: "Hanken Grotesk, Figtree, system-ui, sans-serif"
    fontSize: "20px"
    fontWeight: 600
    lineHeight: 1.25
  body:
    fontFamily: "Hanken Grotesk, Figtree, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Hanken Grotesk, Figtree, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 600
    lineHeight: 1.5
rounded:
  xs: "2px"
  sm: "4px"
  lg: "8px"
  pill: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.blue}"
    textColor: "{colors.surface}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "8px 24px"
    height: "40px"
  button-primary-hover:
    backgroundColor: "{colors.blue-hover}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-secondary}"
    rounded: "{rounded.sm}"
    padding: "8px 24px"
    height: "40px"
  button-danger:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.red-text}"
    rounded: "{rounded.sm}"
    padding: "8px 24px"
    height: "40px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "8px 14px"
    height: "40px"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "20px 24px"
  badge:
    backgroundColor: "{colors.gray-subtle}"
    textColor: "{colors.gray-text}"
    rounded: "{rounded.xs}"
    padding: "0 6px"
  sidebar-link-active:
    backgroundColor: "{colors.blue-subtle}"
    textColor: "{colors.blue}"
    rounded: "{rounded.sm}"
    height: "40px"
---

# Design System: MatHub

## Overview

**Creative North Star: "The Teacher's Desk"**

MatHub looks like a well-kept desk: orderly, practical, and quiet. Everything has a place, nothing is decorative, and the maths content, the teacher's task or the student's exam is always what you notice first. The mood is calm, plain and trustworthy. A student sitting an exam and a teacher marking twenty submissions both need an interface that stays out of the way.

The system is light and flat. White cards sit on a faint blue-grey page, structure comes from hairline borders and spacing rather than shadow, and a single desaturated blue marks what is interactive or current. Corners are small and firm (4px controls, 8px containers). Hanken Grotesk carries all text at a modest range of sizes with weight doing most of the hierarchy work.

The look was inherited from a "Mensa IS Library" Figma file and is not a binding brand. It is documented here as the working incumbent; the owner has said they want depth to become a little softer over time.

**Key Characteristics:**
- Flat, light, border-defined surfaces; shadow only on floating layers.
- One blue accent for links, primary actions and the active nav item.
- Semantic green, orange and red appear only as subtle status tints with dark readable text.
- Compact 40px controls and 14px labels; 16px body.
- Same components on the public site and the teacher panel, only the shell differs (top header vs. 264px sidebar).

## Colors

A restrained palette: one muted blue, cool neutrals, and three status hues used sparingly.

### Primary
- **Slate Blue** (#46689f): links, primary buttons, focus border, active tab and nav item, checkboxes. Hover deepens to **Deep Slate Blue** (#3c5b90).
- **Mist Blue** (#e3e8f1): tinted fill for the active sidebar item, selected rows, hovered secondary buttons and info badges.

### Secondary
- **Chalk Green** (#00a174) with text tone **Pine** (#007857) on **Green Mist** (#dbf2ec): success flashes and "ok" badges.

### Tertiary
- **Amber** (#f18816) with text tone **Burnt Amber** (#9c5409) on **Amber Mist** (#fdeede): warning badges.
- **Marker Red** (#d4605f) with text tone **Brick** (#b03a39) on **Red Mist** (#fbe2e2): errors, destructive actions, and flagged exam-monitoring events.

### Neutral
- **Ink** (#1f2733): primary text and headings.
- **Slate Text** (#4a5666): secondary text, breadcrumbs, inactive tabs and nav.
- **Quiet Gray** (#606d81) on **Cloud** (#eff0f3): default badges and pills.
- **Placeholder Gray** (#8792a3): placeholders and sidebar section eyebrows.
- **Paper** (#ffffff): cards, inputs, header and sidebar.
- **Page Wash** (#f6f8fb): the page background and table header fill.
- **Hairline** (#e2e7ee): borders on cards, lists, tables and dividers. **Field Line** (#d3dae4, hover #b3bfce) for input borders.

### Named Rules
**The One Blue Rule.** Blue means "you can act here" or "you are here". It is never used as decoration or section color.

**The Tinted Status Rule.** Status colors always appear as a pale tint with dark text of the same hue, never as a saturated fill with white text.

## Typography

**Display Font:** Hanken Grotesk (with Figtree, then system sans-serif)
**Body Font:** Hanken Grotesk (same family)
**Label/Mono Font:** none distinct; labels are Hanken Grotesk at 14px semibold.

**Character:** A single clear, slightly humanist grotesk. Hierarchy comes from weight (300 to 700) and size, not from pairing. Figtree is requested at 400 and 500 only as a fallback.

### Hierarchy
- **Display** (700, 36px, 1.25; 28px under 860px width): page titles (h1).
- **Headline** (600, 24px, 1.25): section titles (h2) with 2rem top margin.
- **Title** (600, 20px, 1.25): subsections and the class name in the sidebar.
- **Body** (400, 16px, 1.5): running text and form input text.
- **Label** (600, 14px): form labels, tabs, nav, table headers, breadcrumbs (breadcrumbs at 400).
- **Subtitle** (300, 20px): light lead line under a title.

### Named Rules
**The Weight Over Size Rule.** The scale is short (14, 16, 20, 24, 36). Emphasis comes from weight and ink color before another size is added.

## Layout

Two shells share one component set. The **public site** is a centered column (max 860px, 1100px on material pages with a sticky 220px table of contents) under a thin white header. The **teacher panel** is a 264px sticky white sidebar beside a content area capped at 1040px with 32px/40px padding.

Spacing is loose and rem/px mixed, clustering on 8, 12, 16, 24 and 32px. Lists and forms are single-column; control rows wrap rather than shrink. At 860px and below the sidebar becomes an off-canvas drawer opened from a sticky top bar, padding tightens to 16px, and h1 drops to 28px. At 640px material pages go full-bleed (no side borders or radius) and the table of contents stacks above the text; the connect-A-to-B board widens its gap under 520px. On coarse pointers (phones, tablets) all buttons, fields and nav rows grow to 44px and short text links get a 44px hit area without changing their look; wide content in materials (code blocks, tables) scrolls inside the article instead of widening the page; the viewport covers the notch and side padding respects safe-area insets. Touch controls on mouse-driven screens stay at least 40px tall (32px for compact buttons).

## Elevation & Depth

Flat by default. Surfaces are separated by 1px hairline borders and the white-on-wash contrast, not by shadow.

### Shadow Vocabulary
- **Resting list** (`box-shadow: 0 1px 2px rgba(31, 39, 51, 0.04)`): barely-there lift on the teacher's manage lists only.
- **Floating menu** (`box-shadow: 0 8px 24px rgba(11, 14, 18, 0.12)`): row-action dropdown panels.
- **Drawer** (`box-shadow: 0 0 40px rgba(11, 14, 18, 0.25)`) over a 45% dark scrim: the mobile sidebar.
- **Exam timer** (`box-shadow: 0 2px 8px rgba(11, 14, 18, 0.25)`): the fixed countdown pill during exams, in Ink, turning Brick when time is low.
- **Focus ring** (`box-shadow: 0 0 0 3px rgba(70, 104, 159, 0.18)`): keyboard focus on buttons and links; inputs use a blue border plus a 0.12 alpha ring.

### Named Rules
**The Floating-Only Shadow Rule.** A shadow appears only on something that floats above the page (menu, drawer) or marks focus. Cards and tables stay flat. The owner has asked for slightly softer depth in future; do that by easing these values, not by adding shadows to resting surfaces.

## Shapes

Small and firm. Controls, buttons, flash messages and menu items use a 4px radius; cards, lists, tables, menus and fieldsets use 8px; badges 2px; pills and the resize handle are fully round. Borders are always 1px hairlines; active tabs use a 2px bottom rule. No clipping tricks or decorative geometry.

## Components

### Buttons
- **Shape:** gently squared (4px radius), 40px tall, 8px 24px padding, 16px semibold; compact variant 32px / 14px.
- **Primary:** Slate Blue fill, white text, matching border. Hover deepens to Deep Slate Blue. No transform or shadow.
- **Secondary:** white fill, Slate Text, hairline border; hover turns Mist Blue with blue text.
- **Danger:** white fill, Brick text; hover turns Red Mist. Destruction is never a solid red button.
- **Link / ghost:** transparent, blue text; hover Mist Blue fill.
- **Focus / disabled:** 3px soft blue ring; disabled at 45% opacity.

### Cards / Containers
- **Corner Style:** 8px.
- **Background:** white on the Page Wash, 1px hairline border.
- **Shadow Strategy:** flat, see Elevation.
- **Internal Padding:** 20px 24px for panels; list cards put padding inside the link (16px 20px) so the whole row is the hit target, and hover tints it Page Wash.

### Exam Code Panel
The one place Mist Blue is used as a surface: the student's exam-code form sits in a Mist Blue 8px panel (20px 24px padding), with Ink text and a Quiet Gray input border so the field keeps 3:1 against the tint. It marks the student's single urgent task and is the only filled panel on the public site.

### Inputs / Fields
- **Style:** white, 1px Field Line border, 4px radius, 40px tall, 16px text. Labels above in 14px semibold. Selects use an inline chevron; checkboxes and radios are 18px in Slate Blue.
- **Focus:** border turns Slate Blue with a 3px 12% blue halo. Hover darkens the border.
- **Error / Disabled:** errors surface through a Red Mist flash above the form; disabled fields fill Page Wash with light gray text.

### Navigation
- **Public header:** white bar with hairline bottom border; brand in blue 20px bold, links 14px semibold.
- **Teacher sidebar:** 40px link rows with 20px line icons and 14px semibold labels. Default Slate Text; hover Page Wash; active is Mist Blue fill with blue text. Sections are introduced by 12px uppercase Placeholder Gray eyebrows. Sub-links indent behind a 1px rule.
- **Tabs:** 14px semibold with a 2px blue underline for the active tab.
- **Mobile:** off-canvas drawer behind a CSS-only checkbox toggle, scrim at 45% dark.

### Badges and Pills
Tiny 12px semibold tags with 2px radius in tinted status colors (gray, green, amber, red, blue); pills are round count chips. Submission status uses them as a set: blue for still working, amber for submitted and awaiting marking, green for marked. Gray is for neutral state such as an unpublished draft.

### Lists and Tables
Teacher "manage lists" are bordered 8px cards of 56px-min rows (12px 16px padding, hairline row dividers, hover Page Wash tint) with right-aligned actions and a details/summary row menu. Data tables share the border and radius, with Page Wash header cells in 14px semibold Slate Text.

### Exam question surfaces
Questions use the same inputs and cards. The connect-A-to-B board, the draw tool palette and the fullscreen-required overlay are built from these tokens rather than introducing a separate style.

## Do's and Don'ts

### Do:
- **Do** keep every control at least 40px tall (32px only for compact toolbar actions).
- **Do** use Slate Blue (#46689f) only for interactive or "current" states.
- **Do** express status with a pale tint and dark same-hue text (Pine on Green Mist, Brick on Red Mist).
- **Do** keep surfaces flat with 1px hairline borders; reserve shadow for menus, the drawer and focus.
- **Do** make list rows whole-row click targets and keep actions right-aligned.
- **Do** assume Czech strings run longer than English; let rows and buttons wrap instead of truncating.
- **Do** keep monitoring flags and scores visibly teacher-side only; student-facing screens never show them.

### Don't:
- **Don't** use solid red or green buttons with white text; destructive actions are outlined or tinted.
- **Don't** add shadows to resting cards, tables or inputs.
- **Don't** introduce a second accent color or decorative gradients.
- **Don't** add new font sizes outside 12, 14, 16, 20, 24, 36 without a clear structural reason.
- **Don't** let two near-identical card styles coexist; `.card` and `.panel` should converge.
