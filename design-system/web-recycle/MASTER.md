# Design System Master File — Web Recycle

> **LOGIC:** When building a specific page, first check `design-system/web-recycle/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file. Otherwise, follow the rules below.

---

**Project:** Web Recycle (TCC — sistema de vendas para reciclagem)
**Stack:** PHP (MVC próprio, sem framework) + Bootstrap 5.3 + Bootstrap Icons
**Product type:** Internal B2B admin/CRUD tool (login-gated) — materiais, clientes, fornecedores, balança/pesagens, calendário, vendas
**Category base:** Inventory & Stock Management (industrial slate + recycling green)
**Generated:** 2026-09-14

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable | Bootstrap mapping |
|------|-----|--------------|--------------------|
| Primary | `#334155` | `--color-primary` | `$primary`, sidebar background |
| On Primary | `#FFFFFF` | `--color-on-primary` | |
| Secondary | `#475569` | `--color-secondary` | `$secondary`, inactive nav items |
| On Secondary | `#FFFFFF` | `--color-on-secondary` | |
| Accent (recycling green) | `#059669` | `--color-accent` | `$success` / primary CTA buttons |
| On Accent | `#FFFFFF` | `--color-on-accent` | (white, not black — #059669 fails 4.5:1 with black text) |
| Background | `#F8FAFC` | `--color-background` | `$body-bg` |
| Foreground | `#0F172A` | `--color-foreground` | `$body-color` |
| Card | `#FFFFFF` | `--color-card` | `.card` background |
| Card Foreground | `#0F172A` | `--color-card-foreground` | |
| Muted | `#F2F3F4` | `--color-muted` | table stripes, disabled bg |
| Muted Foreground | `#475569` | `--color-muted-foreground` | helper text, labels |
| Border | `#E2E8F0` | `--color-border` | `$border-color` |
| Destructive | `#DC2626` | `--color-destructive` | `$danger`, delete actions |
| On Destructive | `#FFFFFF` | `--color-on-destructive` | |
| Ring/Focus | `#334155` | `--color-ring` | focus outline |

**Notes:** Slate is the neutral industrial base (matches waste/recycling operations context); `#059669` is the brand/action color — use it deliberately for primary CTAs (Salvar, Registrar, Confirmar compra), not decoratively. Keep `$danger` reserved for destructive actions only (excluir cliente/fornecedor, cancelar venda).

### Cor por módulo (Compras × Vendas)

Compras e Vendas compartilham o mesmo layout de propósito — quem opera aprende uma tela só. Só que isso deixaria as duas indistinguíveis, e trocar uma pela outra faz dinheiro andar na direção errada. Por isso cada módulo tem seu próprio accent:

| Módulo | Accent | Ícone | Selo | Total |
|--------|--------|-------|------|-------|
| Compras | `#0369A1` (azul) | `bi-box-arrow-in-down` | "Entra no estoque" | "Valor a pagar" |
| Vendas | `#059669` (verde) | `bi-box-arrow-up` | "Sai do estoque" | "Valor a receber" |

Azul × verde, e não âmbar × verde: âmbar e verde se confundem na deuteranopia, azul se distingue em todos os tipos comuns de daltonismo. Branco sobre `#0369A1` dá 6.4:1, acima do mínimo AA de 4.5:1.

A cor nunca vem sozinha — ícone, selo e rótulo do total também mudam, atendendo à regra `color-not-only`.

**Implementação:** `theme.css` define `.modulo-compra` / `.modulo-venda` reatribuindo `--color-accent`, e `index2.php` aplica a classe no `<main>` conforme a rota. Como todo o tema consome essa variável, botões, totais, selos e o item ativo do menu acompanham sem markup extra — inclusive nas telas antigas de compra, que ganham a cor do módulo sem serem editadas.

**Migration note:** current `.sidebar { background-color: #1c1c1c }` in `views/*.php` should move to `--color-primary` (`#334155`) so the sidebar reads as part of the same palette instead of pure black. Current `btn-primary` blue (Bootstrap default `#0d6efd`) should be remapped to the accent green via a Sass/CSS variable override, not left as Bootstrap default blue — right now the app mixes Bootstrap-default blue with ad hoc `bg-dark`/`bg-secondary`, which is why it reads inconsistent.

### Typography

- **Heading font:** Lexend
- **Body font:** Source Sans 3
- **Why not the auto-suggested Fira Code/Fira Sans:** that pairing is monospace-flavored, built for code/analytics-heavy dashboards. This app is mostly Portuguese-language forms and CRUD tables, not raw data/metrics — Lexend was designed specifically for reading-fluency/accessibility research, which fits a TCC that will likely be evaluated on usability.
- **Google Fonts:** https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap

```css
@import url('https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap');

body { font-family: 'Source Sans 3', 'Segoe UI', sans-serif; font-size: 16px; line-height: 1.5; }
h1, h2, h3, h4, h5, h6, .navbar-brand, .sidebar h5 { font-family: 'Lexend', sans-serif; font-weight: 600; }
```

### Spacing (align to Bootstrap's existing utility scale, don't invent a new one)

| Token | Value | Usage |
|-------|-------|-------|
| `--space-xs` | `4px` | icon-to-text gaps |
| `--space-sm` | `8px` | form field internal spacing |
| `--space-md` | `16px` | card padding, standard gaps (`p-3`/`gap-3`) |
| `--space-lg` | `24px` | section spacing (`p-4`) |
| `--space-xl` | `32px` | page-level margins |

### Shadows

| Level | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(15,23,42,0.06)` | list rows, inputs |
| `--shadow-md` | `0 2px 8px rgba(15,23,42,0.08)` | cards (`shadow-sm` in Bootstrap terms) |
| `--shadow-lg` | `0 8px 24px rgba(15,23,42,0.12)` | modals, dropdowns |

Keep shadows subtle (Flat/Data-Dense hybrid, not Glassmorphism) — this is an operational tool, not a marketing site.

---

## Layout Pattern (actual app structure, not a landing page)

This is an internal, login-gated CRUD tool. The relevant pattern is **fixed sidebar + content workspace**, repeated per module:

```
┌─────────────┬──────────────────────────────────────┐
│  Sidebar     │  Page header (title + primary action) │
│  (fixed,     ├──────────────────────────────────────┤
│  --color-    │  Filters/search bar (if list page)    │
│  primary)    ├──────────────────────────────────────┤
│  - Início    │  Data table  OR  Form card             │
│  - Materiais │  (index.php)      (create.php/edit.php)│
│  - Clientes  │                                        │
│  - Fornec.   │                                        │
│  - Balança   │                                        │
│  - Vendas    │                                        │
│  - Calendário│                                        │
└─────────────┴──────────────────────────────────────┘
```

Every module (`cliente`, `fornecedor`, `material`, `pesagem`, `venda`) should follow the same three-screen shape: **index** (table + "Novo" CTA top-right), **create**, **edit** (same form as create, pre-filled). Keep this shape consistent when building `views/venda/index.php` — it's currently empty and is the next module to design.

---

## Component Specs (as Bootstrap overrides — restyle existing classes, don't hand-roll new ones)

### Buttons

```css
.btn-primary {
  background-color: var(--color-accent);
  border-color: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 600;
  transition: background-color 150ms ease, transform 150ms ease;
}
.btn-primary:hover { background-color: #047857; border-color: #047857; }
.btn-primary:active { transform: translateY(1px); }

.btn-outline-secondary {
  color: var(--color-secondary);
  border-color: var(--color-border);
}

.btn-danger { background-color: var(--color-destructive); border-color: var(--color-destructive); }
```

### Sidebar

```css
.sidebar {
  background-color: var(--color-primary); /* replaces hardcoded #1c1c1c */
}
.nav-link.active { background-color: var(--color-accent) !important; }
.nav-link:hover { color: #6EE7B7 !important; } /* light green hover instead of arbitrary blue */
```

### Cards

```css
.card {
  background: var(--color-card);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  box-shadow: var(--shadow-md);
}
```

### Forms

```css
.form-control:focus, .form-select:focus {
  border-color: var(--color-ring);
  box-shadow: 0 0 0 3px rgba(51, 65, 85, 0.15);
}
.invalid-feedback { font-size: 0.875rem; }
```

Every `.is-invalid` input must be paired with `aria-describedby` pointing at its `.invalid-feedback` id (currently missing in `login.php` / `alterar_senha.php` / `cliente/create.php` etc. — the error text is visually present but not announced to screen readers).

### Tables (index pages: cliente, fornecedor, material, pesagem)

```css
.table > tbody > tr:hover { background-color: var(--color-muted); }
.table thead th { color: var(--color-muted-foreground); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.02em; }
```

---

## Icons — pick ONE library

The app currently loads **both** Bootstrap Icons and Font Awesome CDNs on the same pages (`views/user/login.php`), but only Bootstrap Icons (`bi-*`) classes are actually used in the markup seen so far. Drop the Font Awesome `<link>` — it's an unused ~80KB render-blocking stylesheet. Standardize on **Bootstrap Icons** everywhere (already the dominant choice in `components/sidebar.php`).

---

## Style Guidelines

**Style:** Flat / Data-Dense Dashboard hybrid — 2D, minimal shadows, no gradients, no glassmorphism. Content density should stay comfortable (this is a small internal tool, not a BI dashboard), but tables should use compact row height (`--table-row-height: 40px`) so more rows are visible without scrolling.

**Avoid:**
- ❌ Mixing icon libraries (Bootstrap Icons + Font Awesome)
- ❌ Hardcoded hex colors in individual view files (`bg-dark`, `#1c1c1c`) — use the CSS variables above in a shared stylesheet instead
- ❌ Glassmorphism/blur, 3D effects, heavy shadows — wrong register for an operational internal tool
- ❌ Emojis as icons
- ❌ Placeholder-only labels — this app already does labels correctly (keep doing that)

---

## Pre-Delivery Checklist

- [ ] Buttons use `--color-accent` (green) for the primary action per screen, not Bootstrap-default blue
- [ ] Sidebar and nav-link active states pull from `--color-primary` / `--color-accent`, not hardcoded hex
- [ ] Only Bootstrap Icons loaded (remove Font Awesome `<link>` unless a `fa-*` icon is actually used)
- [ ] Every `.is-invalid` field has `aria-describedby` linked to its `.invalid-feedback`
- [ ] Text contrast ≥4.5:1 (verify `--color-accent` #059669 against white text — passes; against black text — fails, always use white)
- [ ] Focus states visible on all inputs/buttons (`--color-ring`)
- [ ] New `venda` module follows the same index/create/edit shape as `cliente` and `fornecedor`
- [ ] Responsive check at 375px — sidebar currently `position: fixed; width: 250px` with no collapse on mobile; needs an off-canvas toggle below `768px`
