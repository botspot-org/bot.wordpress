# bot.optimizer — Frontend QA Runbook

Use this file as a checklist and reference when building or modifying any UI in the bot.optimizer WordPress plugin. The goal: every screen a customer sees should feel like the same product. If you're unsure whether something "looks right", run through the relevant section below.

This is not a pixel-perfect spec. It's a set of guardrails so new features land consistently without a dedicated frontend pass.

---

## 1. Global rules

These apply everywhere, no exceptions.

- **Zero border radius.** Every element is `rounded-none`. No `rounded-sm`, no `rounded-md`. If you see a soft corner, it's a bug.
- **No solid color fills on buttons.** Buttons are always outline-based. The only fill is subtle alpha (`bg-white/5`, `bg-mint/[0.06]`). Never a solid white, solid mint, or solid colored button.
- **Transitions on everything interactive.** Every button, input, toggle, link, and row that responds to hover/focus must have `transition-all duration-300`.
- **Font stack.** `Inter` for all UI text. `JetBrains Mono` for monospaced contexts only (timestamps, code, API keys, micro-labels, environment values). Never mix other fonts in.
- **No emojis, no icon fonts.** Icons are inline SVGs from Lucide (or hand-drawn SVG paths matching the Lucide style: `stroke-width="2"`, `stroke-linecap="square"`).

---

## 2. Colors

### Backgrounds

| Token | Hex | Use |
|-------|-----|-----|
| `void` | `#050208` | Page background, dropdown panels, button default bg |
| `abyss` | `#0f0d1a` | Rarely used; only for layered depth where void isn't enough |
| `white/[0.01]` | — | Card backgrounds, empty states |
| `white/[0.02]` | — | Input backgrounds, subtle containers |
| `white/5` | — | Hover backgrounds, badge fills |
| `white/10` | — | Active/selected backgrounds, strong hover |
| `white/20` | — | Pressed states |

### Text

| Token | Use |
|-------|-----|
| `text-white` / `text-white/90` | Primary text, headings, active labels |
| `text-white/80` | Standard body text in controls, log messages |
| `text-white/60` | Secondary text, inactive tab labels |
| `text-white/50` | Descriptions, helper copy |
| `text-white/40` | Tertiary text, section labels, timestamps |
| `text-white/30` | Disabled text, placeholder-level |

### Borders

Use only these:
- `border-white/10` — standard separator, input border, card border
- `border-white/15` — slightly stronger (badges, step numbers)
- `border-white/5` — very subtle (outer container edges)
- `border-white/20` — hover state for inputs
- `border-white/30` — focus state for inputs, hover state for buttons

Never use `border-white/40` or higher. If you need more emphasis, use a semantic color border instead.

### Semantic accents

| Color | Meaning | Example use |
|-------|---------|-------------|
| `mint` (`#d1fef6`) | Success, active, brand, primary action | Connected status, primary CTA border, checked controls |
| `sky` (`#b6d5fe`) | Informational, secondary accent | Gradient pair with mint for hero text |
| `violet` (`#743fe2`) | Attention, review needed | Rarely used; reserved for states that need investigation |
| `emerald-400` | Healthy/live | Alternate success in status contexts |
| `amber-400` / `amber-500` | Warning, stale | Log warnings, degraded status |
| `red-400` / `red-500` | Error, destructive | Log errors, failed status, destructive button borders |

### Mint glow pattern

When mint is used as an active indicator (status dots, checked controls, primary CTAs), pair it with a glow:

```
drop-shadow-[0_0_8px_rgba(209,254,246,0.8)]   /* status dots */
drop-shadow-[0_0_10px_rgba(209,254,246,0.25)]  /* cards, icons */
drop-shadow-[0_0_12px_rgba(209,254,246,0.15)]  /* buttons */
box-shadow: 0 0 8px rgba(209,254,246,0.7)      /* checked controls */
```

Don't apply glow to text or borders — only to small focal elements.

---

## 3. Typography

### Scale

| Context | Classes |
|---------|---------|
| Page title | `text-2xl font-semibold tracking-tight` |
| Card title / section heading | `text-sm font-semibold tracking-tight` |
| Body text | `text-[11px] text-white/50 leading-relaxed` |
| Micro-labels (section headers, badge text, button text, column headers) | `text-[10px] font-mono uppercase tracking-wider` |
| Input text | `text-xs font-mono` (if data) or `text-xs` (if prose) |
| Log lines | `text-[10px] font-mono leading-[1.7]` |
| Environment/metadata values | `text-[10px] font-mono tabular-nums` |

### Rules

- Numbers that appear in columns, tables, timestamps, or stats must use `tabular-nums` so digits don't shift on update.
- Micro-labels (`text-[10px] uppercase tracking-wider`) are the signature "tech" texture. Use them for every section header, column header, and small button. They should feel ubiquitous.
- Never go below `text-[10px]`. Never go above `text-2xl` except for a rare hero moment.

### Hero gradient text

For page-level headings that need emphasis (one per screen max):

```html
<span class="bg-gradient-to-r from-mint to-sky/80 bg-clip-text text-transparent
  drop-shadow-[0_2px_10px_rgba(209,254,246,0.2)]">
  keyword
</span>
```

Use on one word or short phrase within the heading, not the entire heading.

---

## 4. Components

### 4.1 Checkbox

A 14×14px square with no border-radius. When checked, a filled mint square appears inside with a subtle glow. No checkmarks, no ticks, no rounded corners.

```css
/* Base */
appearance: none;
width: 14px; height: 14px;
border: 1px solid rgba(255,255,255,0.25);
background: transparent;
border-radius: 0;
cursor: pointer;
transition: all .2s ease;

/* Hover */
border-color: rgba(255,255,255,0.5);

/* Checked */
border-color: rgba(209,254,246,0.5);
box-shadow: 0 0 10px rgba(209,254,246,0.2);

/* Checked ::after (the inner square) */
content: "";
position: absolute;
inset: 2px;
background: #d1fef6;
box-shadow: 0 0 8px rgba(209,254,246,0.7);
```

**QA check:** Every checkbox on every screen must look identical. If you see a browser-default checkbox, a checkmark tick, or a rounded shape — it's wrong.

### 4.2 Radio

Visually identical to checkbox. Same 14×14 square, same filled inner square when selected. The only difference is the HTML `type="radio"` and `name` grouping behavior.

**QA check:** Radios and checkboxes should be indistinguishable by appearance. The user understands single-vs-multi selection from context (label copy, grouping), not from a visual shape difference.

### 4.3 Toggle switch

A 28×14px rectangular slide switch. No border-radius anywhere. The knob is a 10×10px square that slides left/right.

```css
/* Base */
appearance: none;
width: 28px; height: 14px;
border: 1px solid rgba(255,255,255,0.2);
background: transparent;
position: relative;
cursor: pointer;
transition: all .3s ease;
border-radius: 0;

/* Knob (::after) — off */
content: "";
position: absolute;
top: 1px; left: 1px;
width: 10px; height: 10px;
background: rgba(255,255,255,0.5);
transition: all .3s ease;

/* Checked (on) */
border-color: #d1fef6;
background: rgba(209,254,246,0.08);

/* Knob — on */
left: 15px;
background: #d1fef6;
box-shadow: 0 0 8px rgba(209,254,246,0.7);
```

**When to use:** Toggle for binary on/off states that take effect immediately or are clearly "mode switches" (debug logging, verbose runtime). Use checkbox when the state is part of a group or saved via a button.

### 4.4 Buttons

**Standard action (outline):**
```
h-8 or h-9
px-4 or px-5
border border-white/10
bg-void
text-white/70
hover:bg-white/5 hover:text-white hover:border-white/30
text-[10px] font-mono uppercase tracking-wider
transition-all duration-300
```

**Primary action (mint outline):**
```
border border-mint/40
bg-mint/[0.06]
text-mint
hover:bg-mint/10 hover:border-mint/70
drop-shadow-[0_0_12px_rgba(209,254,246,0.15)]
```

Use primary for the single most important action on a screen (Save, Connect, Test). Max one primary button visible at a time. If two actions compete, one is primary and the other is standard.

**Destructive action:**
```
bg-red-500/10
border border-red-500/50
text-red-400
hover:bg-red-500/20 hover:border-red-500
```

**Icon-only button (ghost):**
```
h-6 w-6 or h-7 w-7
p-0
border border-white/10 bg-void
hover:bg-white/5 hover:border-white/30
text-white/60 hover:text-white
```

Icon-only buttons must have a `title` attribute for accessibility and hover context.

**Rules:**
- Never use a solid filled button (no `bg-white`, `bg-mint`, `bg-blue-500` etc.)
- Button text is always `text-[10px] font-mono uppercase tracking-wider`
- Height is `h-8` (standard) or `h-9` (prominent). Never taller
- If a button has an icon + text, use `gap-2` between them

### 4.5 Inputs

```
border border-white/10
bg-transparent or bg-white/[0.02]
px-3 py-2 or py-3
text-xs font-mono (for data) or text-xs (for prose)
text-white/90
placeholder:text-white/30
hover:border-white/20
focus:outline-none focus:border-white/30
transition-colors
```

For composite inputs (icon prefix, suffix button), wrap in a flex container with the border on the wrapper, not individual parts. Internal dividers use `border-l border-white/10` or `border-r border-white/10`.

### 4.6 Dropdown / select

```
h-6 or h-7
border border-white/10
bg-void
text-[10px] font-mono uppercase tracking-wider text-white/60
px-2
hover:border-white/20
focus:outline-none
appearance-none
```

For native `<select>`, add a custom chevron via `background-image` SVG. For custom dropdowns, the floating menu uses:

```
bg-void
border border-white/10
shadow-xl
text-xs
```

Menu items:
```
px-3 py-1.5
hover:bg-white/5 hover:text-white
cursor-pointer
transition-colors
```

### 4.7 Cards

Cards are containers with `border border-white/10 bg-white/[0.01]`. They have:
- A header row: `h-[42px] border-b border-white/10 flex items-center px-4` with a micro-label title
- Content area: `p-4` or `p-5`
- Optional footer: `border-t border-white/10 p-4`

For emphasis cards (like "Next steps" after connection), use `border-mint/25` instead of `border-white/10` and optionally add a subtle grid texture behind the content.

Never put cards inside cards. Keep nesting flat.

### 4.8 Status indicators

Status dots are `h-1.5 w-1.5` squares (no border-radius) filled with the semantic color.

For the primary live indicator, add a pulse animation:

```css
@keyframes pulse-mint {
  0%,100% { box-shadow: 0 0 0 0 rgba(209,254,246,0.6); }
  50%     { box-shadow: 0 0 0 4px rgba(209,254,246,0); }
}
```

Use pulse sparingly — only for the single most important "alive" signal (connection status). Other status dots are static.

Status states:
- **Healthy:** mint dot, mint text
- **Warning:** amber dot, amber text
- **Error:** red dot, red text
- **Inactive/unknown:** `bg-white/20` dot, `text-white/40` text

---

## 5. Layout patterns

### Header bar
Fixed `h-[57px]`, `border-b border-white/10`, `flex items-center justify-between px-6`.
Left side: product name + context. Right side: status indicators + domain.

### Tab bar
Fixed `h-[42px]`, `border-b border-white/10`, `flex items-center px-6`.
Tabs are `text-[10px] font-mono uppercase tracking-[0.18em]`.

Active tab: `text-white` with a bottom underline (`h-px bg-mint shadow-[0_0_10px_rgba(209,254,246,0.8)]` absolutely positioned at `bottom-0`).

Inactive tab: `text-white/40 hover:text-white/80`.

Right-aligned tabs (like Developer) use `ml-auto` to push to the far right.

### Two-column form layout
For settings-style screens: `grid grid-cols-[200px_1fr] gap-8`. Left column holds the section micro-label and description. Right column holds the controls. Sections are separated by `border-t border-white/10 py-6`.

### Two-pane layout
For log/detail screens: `grid grid-cols-[1fr_320px]`. Left pane is the primary content (logs, list). Right pane is the sidebar (controls, metadata). Separated by `border-r border-white/10` on the left pane.

### Section sub-headers
Inside developer or settings panes: `text-[10px] font-mono uppercase tracking-wider text-white/40 mb-3`.

### Vertical separators
Inline between items: `<div class="h-3 w-px bg-white/10"></div>` or `h-4 w-px`.

---

## 6. Content & copy

### Tone
Calm, operational, non-technical. The plugin should feel like a utility that just works, not a product that demands attention.

### Patterns
- Descriptions under section headers: one short sentence, `text-white/40` or `text-white/50`
- "(recommended)" labels: inline, same line as the option, `text-white/30`, never in a separate callout box
- Links that leave the plugin: always append an arrow icon (↗ via SVG), use `text-white/40 hover:text-white/80`
- "Managed in Botspot": use this phrase when a feature lives in the platform, not "Go to Botspot to…" or "This is configured in…"

### Words to avoid in customer-facing surfaces
- "Debug", "verbose", "logs", "cache TTL", "force sync", "diagnostics" — these belong in the Developer tab only
- "Schema", "JSON-LD", "injection", "hook" — use plain equivalents ("SEO data", "output", "placement") outside Developer
- "Error" as a heading — use "Something went wrong" or "Connection issue"
- "Admin", "superuser", "root"

---

## 7. Production / customer-facing QA checklist

Run through this before any release. Every item is pass/fail.

### Visual consistency
- [ ] All corners are sharp (zero border-radius on every element including inputs, buttons, badges, cards, tooltips)
- [ ] No solid-fill buttons anywhere
- [ ] All checkboxes render as filled mint squares when checked (no browser defaults, no checkmarks)
- [ ] All radios render identically to checkboxes
- [ ] Toggles are rectangular slide switches (no pill shapes)
- [ ] All interactive elements have `transition-all duration-300`
- [ ] All micro-labels are `text-[10px] font-mono uppercase tracking-wider`
- [ ] No font other than Inter or JetBrains Mono renders on any screen
- [ ] Numerical columns and timestamps use `tabular-nums`

### Color correctness
- [ ] Page background is `void` (#050208), never pure black (#000) or gray
- [ ] No color appears that isn't in the color table above
- [ ] Mint is only used for: active/success states, primary CTA borders, checked controls, brand gradient. Never decorative
- [ ] Status dots use the correct semantic color for their state
- [ ] Amber/red only appear when something is actually wrong. Never as decoration

### Developer exposure
- [ ] No log output, debug toggles, cache controls, environment info, or technical diagnostics are visible outside the Developer tab
- [ ] The Developer tab is clearly separated (far-right tab position) from customer-facing tabs
- [ ] No raw API responses, error stack traces, or PHP/WP version numbers are visible outside Developer
- [ ] No `console.log` output in production builds
- [ ] Error messages shown to customers are human-readable, not technical (e.g., "Couldn't connect — check your API key" not "401 Unauthorized")

### Interaction
- [ ] Only one primary (mint) button visible per screen at a time
- [ ] Every icon-only button has a `title` attribute
- [ ] Hover states are visible and consistent (check buttons, inputs, tabs, links, table rows)
- [ ] Focus states don't show browser-default blue outlines (use `focus:outline-none` + border change)
- [ ] Tab switching is instant (no loading spinners for local state changes)

### Content
- [ ] No technical jargon outside the Developer tab (see "Words to avoid" above)
- [ ] Every screen with platform-owned features says "Managed in Botspot", not duplicated controls
- [ ] "(recommended)" appears inline next to the option, not in a separate card or tooltip
- [ ] No empty states show a blank screen — always a container with an icon, title, and one-line description

### Spacing
- [ ] Header is exactly `h-[57px]`
- [ ] Tab bar is exactly `h-[42px]`
- [ ] Card headers are `h-[42px]`
- [ ] Section separators are `border-t border-white/10` (never thicker, never colored)
- [ ] Page content padding is `p-8` (or `p-6` in sidebar contexts)

---

## 8. Adding a new feature

When you add a new control, setting, or screen:

1. **Decide where it lives.** Is it customer-facing (Connect or Settings) or developer/debug (Developer tab)? If you're unsure, it's probably Developer. When in doubt, hide it.
2. **Pick existing components.** Use checkbox, radio, toggle, button, input, or card from this doc. Don't invent a new control pattern unless none of these fit.
3. **Write copy first.** One micro-label heading, one short description sentence, label text for each option. Run through the "Words to avoid" list.
4. **Match the layout.** Settings → two-column form grid. Developer → add to the sidebar controls section or add a new log category. Connect → don't touch unless it's connection-related.
5. **Run the checklist above** on your feature in isolation before merging.

If a new component pattern is genuinely needed (something not covered here), document it in this file before shipping so the next feature can follow the same pattern.