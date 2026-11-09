# DESIGN_SYSTEM.md — SplitWise+ ("Hisaab")

Phase 4. Visual design of the 16 working screens. Implementation: `public/assets/css/app.css` (tokens + components on top of Bootstrap 5.3), fonts in `public/assets/vendor/fonts/`.

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
desktop ≥ 992px                          mobile < 992px
┌────────┬──────────────────────────┐    ┌──────────────────────┐
│ brand  │ page title      actions  │    │ ≡  SplitWise+   🔔   │
│ + Add  │                          │    ├──────────────────────┤
│ nav    │ hero sentence + figure   │    │ page title           │
│ …      │ IOU slips  │ activity    │    │ hero                 │
│        │            │             │    │ IOU slips            │
│ user   │                          │    │ activity (rows)      │
└────────┴──────────────────────────┘    └──────────────────────┘
```
- Left rail (232 px, lavender) on desktop; the same nav in an off-canvas drawer on mobile.
- Content left-aligned, max 1100 px. Amounts right-aligned.
- Lists of expenses are **rows**, not tables, so they read the same on a phone.

## Principles
1. **The memorable thing is the IOU slip.** Each person-to-person debt is a row: initials, a sentence, the amount in Baloo, and a coloured edge (green = they owe you, red = you owe). It appears on the dashboard, balances and group pages. Everything else stays quiet.
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
