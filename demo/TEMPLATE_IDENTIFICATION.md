# How to Identify Which Template Is Used

## 1. Main app (root `n.php` – current live MiniWebsites)

Template is stored in the **database** per card.

### Step 1: Get the card record

- **By card ID (slug):**  
  `SELECT * FROM digi_card WHERE card_id = 'customer_card_id';`
- **By numeric id:**  
  `SELECT * FROM digi_card WHERE id = 123;`

### Step 2: Read the template/theme field

- The column that defines the theme is **`d_css`** in the **`digi_card`** table.
- Example values: `panel/card_css1.css`, `panel/card_css2.css`, … (one CSS file per theme).

### Step 3: Map to a human‑readable name (optional)

- In **user/website/select-theme.php** the mapping is: image → `d_css` value.
- So the “template” for a card = whatever CSS filename is stored in **`digi_card.d_css`** for that card.

### Summary (main app)

| What        | Where                | How to see it                          |
|------------|----------------------|----------------------------------------|
| Template   | `digi_card.d_css`     | Query the card row and read `d_css`    |
| Who sets it| User                 | user/website/select-theme.php (Save)   |
| Used when  | Root n.php            | n.php loads that CSS file for the card |

---

## 2. Demo app (demo folder – MW templates: desi_dukaan_green, beauty_salon)

Template can be set by **URL** and (optionally) by **database**.

### Step 1: Check the URL

- Demo URL format:  
  `.../demo/n.php?n=card_id&template=TEMPLATE_ID`
- **`template=TEMPLATE_ID`** is the template for that view.
- Examples:
  - `.../demo/n.php?n=abc123&template=beauty_salon`  → **beauty_salon**
  - `.../demo/n.php?n=abc123&template=desi_dukaan_green`  → **desi_dukaan_green**
- If **`template`** is omitted, the demo uses the **default** template (e.g. `beauty_salon`).

So: **to identify which template is used in the demo, look at the `template=` parameter in the URL.**

### Step 2: See it on the page

- The demo page can show a small label, e.g. **“Template: beauty_salon”** in the footer or in a small badge, so you can confirm without checking the URL.

### Step 3 (optional): Store template per card in DB

- The demo **already checks** for a column **`mw_template_id`** in **`digi_card`**. If that column exists and is set for a card, demo/n.php uses it when `?template=` is not in the URL.
- To use it: add column  
  `ALTER TABLE digi_card ADD COLUMN mw_template_id VARCHAR(64) DEFAULT NULL;`  
  then set values e.g. `beauty_salon` or `desi_dukaan_green` per card.
- Then “which template” = **`digi_card.mw_template_id`** for that card (and you can show it in admin or user dashboard).

### Summary (demo)

| What        | Where                     | How to identify it                          |
|------------|----------------------------|---------------------------------------------|
| Template   | URL `?template=...`        | Look at the `template` query parameter      |
| On page    | Footer / badge             | Text like “Template: beauty_salon”          |
| Optional   | DB column `mw_template_id` | Query `digi_card` for that card             |

---

## Quick reference

- **Main app (live site):** which template = **`digi_card.d_css`** for that card.  
- **Demo:** which template = **`template=` in the URL** (and optionally **`digi_card.mw_template_id`** if you add it).
