# Design

UI language follows the "Factory EOS" reference module: dense, calm, teal-accent enterprise UI. Source of truth = `public/css/oilpms.css` (extracted from prototype).

## Tokens (light)
| Token | Value | Use |
|---|---|---|
| `--bg` | `#f6f5f1` | app background |
| `--card` | `#fff` | cards |
| `--ink` / `--mut` | `#17201f` / `#6b7775` | text / muted |
| `--line` | `#e4e2da` | borders |
| `--nav` / `--nav2` / `--navink` | `#0f1b1d` / `#16282b` / `#b7c6c4` | sidebar |
| `--acc` / `--acc2` / `--accbg` | `#0f766e` / `#14b8a6` / `#e3f3f1` | primary |
| `--ok` `--warn` `--bad` `--info` | `#15803d` `#b45309` `#b91c1c` `#1d4ed8` | status (each has `*bg` tint) |
| `--r` | `6px` | radius |
| `--font` | Inter, system-ui, Segoe UI | typography |
Dark theme: `data-theme="dark"` on `<html>` or `prefers-color-scheme: dark`; toggled via avatar menu.

## Layout
`aside.side` (collapsible, mobile drawer) · `.main` → topbar (global search, **Create** menu, notifications bell, avatar/role switcher) → `.content` (breadcrumb, `.ph` page header with title/sub/actions, role-readonly banner, `#pgbody`) · mobile bottom bar `.mbar` (Home, Cases, RFQ, CS, Approve).
Supplier portal renders full-width without sidebar.

## Components (prototype classes)
`.card/.cp`, `.grid g2/g4/g21`, `.tbl/.tw` tables (search, column filters, sort, column toggle, saved views, pagination, row selection), `.pill p-ok|p-warn|p-bad`, `.alert info|warn|bad|ok`, KPI row, `.steps` (workflow stepper), `.tabs`, `.tl` timeline, `dlg` modal dialogs with `F()` form fields, toasts (with action), skeleton loaders, `.chk` check rows, `.podoc` PO document preview.
Icons: inline SVG stroke 1.8, 24-grid, set `IC` (~50 icons).

## Interaction rules
- Role-restricted actions show a toast explaining segregation of duties + "Switch role" shortcut (demo only).
- Destructive/locking actions use confirm dialog with danger style and explicit consequence text.
- Remarks fields marked required for return/reject.
- Dates dual-displayed: Bangladesh BST and supplier CST.
- Sensitive values always `[PLACEHOLDER]` in mock; masked in real UI.

## Accessibility / responsive
Keyboard: Esc closes dialogs/menus, Enter in search jumps to first result. Min touch target on mobile bar. Provide visible focus and ≥4.5:1 contrast when adjusting tokens.

## Navigation (11 groups)
Command Center · Purchase Cases · Requisitions · Sourcing/RFQ · Negotiation & Awards · Purchase Orders · Import & Warehouse · Suppliers · Reports · Control · Administration — filtered per role by `ROLE_NAV`.
