# UI_DESIGN_SYSTEM.md — SplitWise+ ("Hisaab")

Phase 4, refined in Phase 5.5 (renamed from DESIGN_SYSTEM.md). Visual design of the 16 working screens. Implementation: `public/assets/css/app.css` (tokens + components on top of Bootstrap 5.3), fonts in `public/assets/vendor/fonts/`.

## Brief
- **Subject:** a shared-money ledger for Indian college friends, roommates and trip groups.
- **Audience:** students and young professionals, mostly on phones, comfortable with UPI.
- **Primary job:** answer *"who owes whom, and how much?"* at a glance, then let you add an expense or settle up in a couple of taps.

## Concept
**Hisaab** — the paper *hisaab-kitaab* habit, moved onto the phone. Every balance is written as a plain sentence ("Rohan owes you ₹6,012.50"), money is set large in a rounded Indian display face, and the colours come from Indian rupee notes.

## Colour (from the notes in your wallet)
| Token | Hex | Role | Source |
|---|---|---|---|
| `--ink` | `#211C3A` | text, headings | deep indigo ink |
| `--paper` | `#F5F3FA` | page background | cool lavender-white |
| `--lavender` | `#E6E0F5` | navigation rail, selected rows, tints | ₹100 note |
| `--violet` | `#4B3A9C` | primary actions, links, chart series 1 | ₹100 note, deepened |
| `--marigold` | `#F4B400` | the one accent: **settle-up** actions and *pending* states | ₹200 note |
| `--gets` / `--owes` | `#0E7A55` / `#B42318` | money you get back / money you owe | ledger convention |
| `--line` | `#DCD6EA` | borders, dividers | — |
| `--muted` | `#5E5878` | secondary text | — |

Contrast on white: ink 15.6:1, violet 8.9:1, gets 5.4:1, owes 6.3:1, muted 6.6:1; ink on marigold 10.2:1. Gets/owes are always paired with words ("gets back", "owes"), never colour alone.

## Type
- **Baloo 2** (Ek Type, Mumbai) 600–800: page titles and money figures. Rounded, friendly, Indian.
- **Mukta** (Ek Type) 400/500/700: everything else. Tabular figures in amount columns.
- Scale (×1.25): 13 / 15 / 16 body / 20 / 25 / 31 / 39 px. Sentence case everywhere; no all-caps labels.

## Layout
```
wide/laptop ≥ 992px                        tablet/mobile < 992px
┌────────┬──────────────────────────────┐  ┌──────────────────────┐
│ brand  │ Page title          actions  │  │ ≡  SplitWise+   🔔   │
│ + Add  │ ┌────────┬───────┬───────┐   │  ├──────────────────────┤
│ nav    │ │summary │ band  │       │   │  │ Page title           │
│ …      │ └────────┴───────┴───────┘   │  │ summary band (2×2)   │
│        │ slips/ledger │ activity      │  │ ledger               │
│ user   │  (two columns only ≥1200px)  │  │ table → labelled rows│
└────────┴──────────────────────────────┘  └──────────────────────┘
```
- Left rail (232 px, lavender) from 992 px; the same nav in an off-canvas drawer below.
- Content left-aligned, max 1100 px. Amounts right-aligned with tabular figures.
- **Breakpoints checked:** 1440 (wide), 1366, 1024 (laptop), 768 (tablet), 390 (phone). `.grid-2` stays one column until **1200 px** — at 1024 two columns squeezed the slips unreadably.

## Components (Phase 5.5)
| Component | Where | Rule |
|---|---|---|
| **Summary band** (`dl.summary`) | dashboard, group, balances | One bordered strip of 3–5 figures split by hairlines. The key item (`.is-key`) carries the signed net figure **plus words** ("+₹9,991.67 you get back", "₹0.00 settled up", "−₹1,060.00 you owe") from `net_parts()`. Replaces the greeting hero ("Hi Priya…"). 2 columns on phones. |
| **Ledger** (`.slips`) | who-owes-whom, suggestions | One bordered list with dividers and a 3 px green/red edge per row — not a stack of separate cards. |
| **Data table** (`.data-table`) | expenses list, settlements | Real `<table>` with Date, Description, Paid by, Group, Total, Your share / Payment, Status, Amount, Actions. Below 768 px each row becomes a labelled block (`td[data-label]::before`) instead of a horizontal scroller. Deleted rows dimmed with a "deleted" pill; signed amounts in settlements (+ received, − paid) with a one-line key. |
| **Rows** (`.rows`) | dashboard recent expenses, group activity | Short date column + sentence + amount with "your share". Kept where only 5–6 items show. |
| Members table | group page | Name, role pill, "paid ₹X · share ₹Y" line, balance column — fits half width without clipping. |

## Principles
1. **The memorable thing is the IOU ledger.** Each person-to-person debt is a ledger row: initials, a sentence, the amount in Baloo, and a coloured edge (green = they owe you, red = you owe). It appears on the dashboard, balances and group pages. Everything else stays quiet.
2. **Sentences over labels.** "You get back ₹29,825.66", "Sneha pays Aarav ₹7,133.33". No eyebrow labels, no "A · B · C" meta strings, no arrows tacked onto link text.
3. **Marigold means settle up.** It is used only for settle-up buttons and pending payments, so it always means "money still to move".
4. **Hierarchy, not identical cards.** Main panels are white with a hairline border; secondary lists sit directly on the page. Radii: 12 px panels, 8 px controls, full pills for status.
5. **Quality floor:** visible focus ring (violet, 3 px), works at 360 px wide, `prefers-reduced-motion` respected, no information by colour alone.

## Review against the generic look (what changed)
- First draft had three stat cards ("Overall / You are owed / You owe") — the default big-number-in-a-card kit. **Replaced** by one hero sentence with the figure inside it plus the IOU slips.
- First draft used Bootstrap's blue and system font — **replaced** by note-derived violet/marigold and Ek Type faces.
- Suggestions were "Sneha → Aarav"; **now** "Sneha pays Aarav" (the arrow carried meaning only for sighted users).
- Avoided: cream background, terracotta accent, near-black + acid accent, newspaper hairline grid, uppercase eyebrows, monospace data labels.

## Charts (dataviz method)
- Category share and group totals: **horizontal bars, one hue** (violet) — magnitude, not identity, so no categorical palette and no doughnut.
- Monthly trend: one 2 px violet line, no legend (the title names it).
- Paid vs share, sent vs received: two series, violet + marigold, validated with the dataviz palette validator; legend always shown; each chart has a "Show as table" view.

## Phase 5.5 review: less "generated", more ledger
Audit at 1440/1024/768/390 found these generic patterns; each was changed:
| Found | Why it read as template | Change |
|---|---|---|
| "Hi Priya 👋"-style hero with one huge number | marketing landing pattern, wastes the first screen | summary band with four real figures, incl. this month's spending |
| IOU slips as separate floating cards | identical card stack | one ruled ledger |
| Long expense/settlement lists as card rows on wide screens | hard to scan or compare amounts | data tables with right-aligned money; labelled rows on phones |
| 2.5 rem page titles | oversized for a working tool | `clamp(1.6rem, …, 2rem)` |
| Two columns at 1024 px | slips and figures overflowed | two columns only ≥1200 px |
| Members table clipped balance text at half width | horizontal scroll inside a panel | paid/share moved under the name |
Accessibility after the change: axe-core 0 violations on all screens (desktop + phone); no inline scripts (CSP `script-src 'self'`).
