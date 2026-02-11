# MiniWebsite Demo (MW_All Template Guide)

This folder is the **reference implementation** of the MiniWebsite (MW) platform as per the MW_All / MW_Full Template Guide.

## Core principles

- **HTML / JS / Components** = same for all templates
- **Template difference** = `config.json` + `theme.css` only
- **Mobile-first** UX; WhatsApp = primary conversion action
- **Responsive**: Mobile (≤767px), Tablet (768–1023px), Desktop (≥1024px)

## Section order (13 sections)

1. Hero / Business Header  
2. Quick Action Buttons  
3. About Business  
4. Products with Pricing (category-based)  
5. Services  
6. Videos  
7. Image Gallery  
8. Payment QR  
9. Contact & Location (SEO/GEO)  
10. Social Icons  
11. Footer  
12. Sticky Bottom Navigation  
13. Floating WhatsApp CTA  

## How to run

- **Static demo:** open `index.html` in a browser (or serve the `demo` folder via XAMPP/Apache).  
  Example: `http://localhost/miniwebsite/demo/`
- **Dynamic demo (DB):** open `n.php?n=card_id` to bind real data from the main app database.  
  Example: `http://localhost/miniwebsite/demo/n.php?n=your_card_id`  
  If `card_id` is missing or not found, sample “Glamour Beauty Salon” data is used so the demo always renders.
- **Config editor:** open `config-editor.php` to change layout, section order, and all template options without editing JSON by hand.  
  Example: `http://localhost/miniwebsite/demo/config-editor.php`  
  Pick a template (e.g. desi_dukaan_green or beauty_salon), edit form fields, then click **Save config** to write `templates/{template}/config.json`.

## File structure

```
demo/
├── index.html              # Static single page, all sections
├── n.php                   # Dynamic demo – same layout, data from DB (or sample)
├── config-editor.php       # UI to edit template config.json (layout, section order, options)
├── css/
│   └── components.css     # Responsive core UI (variables only, no colors)
├── js/
│   └── mw-core.js         # MW_SelectionStore, modals, sticky nav, WhatsApp CTA
├── templates/
│   └── desi_dukaan_green/
│       ├── config.json    # Section order, toggles, layout flags
│       └── theme.css      # Colors, fonts, visual tokens
└── README.md
```

## Features implemented

- **Hero**: Logo, business name, primary category, location  
- **Quick actions**: Call, WhatsApp, Direction, Email, Share, Save contact, Scan QR  
- **Products**: Category bar (left), product list (grid on tablet/desktop), ADD → ✔ ADDED, fullscreen product modal with same-category swipe  
- **SelectionStore**: Global; selection persists across category switch; clears on refresh  
- **Floating WhatsApp CTA**: Default = normal chat; with selection = “Send X Products” with auto-generated message  
- **Sticky nav**: Home, About, Shop, Videos, Gallery, Pay; hidden on desktop (1024px+)  
- **SEO**: Title, meta description, canonical, single H1, H2 per section  
- **GEO**: Structured address, map link  
- **AEO**: Hidden FAQ block + JSON-LD FAQPage schema  

## Using this to enhance `n.php`

- Replace or mirror section structure in `n.php` with the same section IDs and class names.
- Load `components.css` and a theme (e.g. from `templates/desi_dukaan_green/theme.css`).
- Populate sections from DB (`digi_card`, `card_product_pricing`, etc.) while keeping the same HTML structure.
- Add `mw-core.js` (or equivalent logic) for product selection, modals, and WhatsApp CTA.

## New template (same behaviour, new look)

1. Copy `templates/desi_dukaan_green/` to e.g. `templates/beauty_salon/`.
2. Change `template_id` and `template_name` in `config.json`.
3. Edit only `theme.css` (colors, fonts). Keep `components.css` shared.
