## Project Reorganization Plan (Old → New Structure)

This document describes what will be done to reorganize the existing `old/` PHP project into the new unified structure (without changing functionality or design, and with clean URLs that hide `.php`).

### 1. Goals
- **Unify user areas**: Merge `customer/`, `franchisee/`, and `team/` into a single `user/` area with role-based behavior.
- **Consolidate shared layout**: Use a single `header.php` and `footer.php` for user area and one for public site where needed.
- **Centralize configs**: Move scattered `connect.php` and payment configs into `app/config/` with reusable includes.
- **Unify assets**: Move CSS/JS/images/fonts into an organized `assets/` tree.
- **Unify payment flow**: One `payment_page/` with role-aware logic and a single Razorpay SDK under `vendor/`.
- **Clean URLs**: Access pages without `.php` in the URL using Apache routing (`.htaccess`).

### 2. Target Folder Structure (High Level)
- **`public/`**
  - Public-facing pages (home, static pages, landing pages).
  - Public `header.php` / `footer.php` if separate from user area.
- **`app/`**
  - `config/database.php`: single DB connection.
  - `config/payment.php`: payment configuration.
  - `helpers/role_helper.php`: functions to detect current user role and guard routes.
  - Other shared helpers/functions as needed.
- **`admin/`**
  - Existing admin panel files moved/adjusted as needed but keeping functionality same.
- **`panel/`**
  - `panel/login/` and `panel/franchisee-login/` kept for login flows, updated to redirect into `/user/...` URLs.
- **`user/` (unified area for customer, franchisee, team)**
  - `includes/header.php`, `includes/footer.php` (role-based visibility).
  - `dashboard/`, `kit/`, `website/`, `profile/`, `collaboration/`, `referral/`, `verification/`, `teams/`, `wallet/` (role-based content).
- **`assets/`**
  - `css/`, `js/`, `images/`, `fonts/`, `uploads/` (all static assets consolidated here).
- **`payment_page/`**
  - `pay.php`, `verify.php`, `callback.php`, plus any specific flows (joining deals, etc.).
- **`vendor/`**
  - Keep Composer-managed dependencies, including `vendor/razorpay/razorpay/` as the single Razorpay SDK.

### 3. Phased Migration Plan (What I Will Do)

#### Phase 1: Create New Base Structure (No Logic Changes Yet)
- Create new top-level folders: `public/`, `app/`, `user/`, `assets/` (if not already), and ensure `vendor/` remains.
- Add empty (or stub) files where needed:
  - `app/config/database.php` (will later centralize DB connection code).
  - `app/config/payment.php` (will later centralize payment configuration).
  - `app/helpers/role_helper.php` (role detection; will re-use existing session/auth logic).
- Copy existing root-level public files (like `index.php`, static HTML pages) into `public/` (keeping originals temporarily until final cut-over).

#### Phase 2: Unified User Area (`user/`)
- Create `user/includes/header.php` and `user/includes/footer.php`:
  - Start from one of the existing role headers/footers (e.g. `old/customer/header.php`) and merge the differences from `franchisee` and `team` so they are controlled via role checks.
  - Implement role-based sections (menu items that appear/disappear based on `customer`, `franchisee`, `team`).
- Create `user/dashboard/index.php`:
  - Merge logic from `old/customer/dashboard/index.php`, `old/franchisee/dashboard/index.php`, and `old/team/dashboard/index.php`.
  - Use `role_helper.php` to render role-specific cards/sections.
- Create other unified directories and entry points:
  - `user/kit/index.php` (from `old/customer/kit/index.php`, `old/franchisee/kit/index.php`, `old/team/kit/index.php`).
  - `user/website/*.php` (move/merge from `old/customer/website/` and `old/team/website/` as needed).
  - `user/profile/`, `user/collaboration/`, `user/referral/`, `user/verification/`, `user/teams/`, `user/wallet/` mapped from respective old folders.
- Update includes inside new `user/` files to use:
  - `require_once '../../app/config/database.php';`
  - `require_once '../../app/helpers/role_helper.php';`
  - `include '../includes/header.php';` / `include '../includes/footer.php';`
- Keep original `old/customer/`, `old/franchisee/`, `old/team/` folders intact until everything is verified.

#### Phase 3: Centralize Database and Configs
- Analyze existing `connect.php` files:
  - `old/connect.php`, `old/admin/connect.php`, `old/panel/login/connect.php`, and any others.
- Create a single `app/config/database.php` that:
  - Defines DB credentials and opens the connection (PDO or mysqli, matching current project style).
  - Optionally exposes a simple helper function like `get_db()` or `$conn` variable via include.
- Gradually update:
  - New `user/` files to use `app/config/database.php`.
  - Admin and panel scripts to include the central config instead of local `connect.php` copies (where safe and compatible).
- Leave old `connect.php` files temporarily for backward compatibility until all critical paths are migrated; then remove duplicates.

#### Phase 4: Consolidate Assets
- Create the unified `assets/` structure:
  - `assets/css/`, `assets/js/`, `assets/images/`, `assets/fonts/`, `assets/uploads/`.
- Move existing assets from:
  - `old/assets/`, `old/common/assets/`, `old/admin/assets/`, `old/customer/assets/`, `old/icon_fonts/`, `old/images/`, etc.
  - Deduplicate any obvious copies (e.g., `assets/css - Copy/` to be removed).
- Update asset references in templates and PHP files:
  - Replace scattered paths with unified `/assets/...` paths.
  - Verify that all layouts and design remain unchanged.

#### Phase 5: Payment Consolidation
- Create unified `payment_page/` under new structure (can reuse existing `old/payment_page/` as base):
  - `payment_page/pay.php`
  - `payment_page/verify.php`
  - `payment_page/callback.php` (or equivalent result/return handlers).
  - Include any special payment flows (joining deal, franchisee distributor, etc.) as separate scripts but sharing common config and Razorpay library.
- Move/merge payment detail pages:
  - Consolidate `customer/website/payment-details.php` and `team/website/payment-details.php` into `user/website/payment-details.php` with role-aware behavior if needed.
- Centralize payment configuration:
  - Move duplicated `config.php` payment files into `app/config/payment.php`.
  - Update `pay.php`/`verify.php` to include `app/config/payment.php`.
- Use a single Razorpay SDK:
  - Keep `vendor/razorpay/razorpay/` (Composer-managed).
  - Remove duplicate `razorpay-php/` folders under `panel/login/payment_page/` and `panel/franchisee-login/payment_page/` after updating code to use the vendor autoloader.

#### Phase 6: URL Routing and Removing `.php`
- Configure Apache `.htaccess` at the project/public root to:
  - Rewrite clean URLs like `/dashboard`, `/kit`, `/website`, `/profile`, `/collaboration`, `/referral`, `/verification`, `/teams`, `/wallet` to the appropriate PHP scripts under `user/`.
  - Example (conceptual):
    - `/dashboard` → `user/dashboard/index.php`
    - `/kit` → `user/kit/index.php`
    - `/website` → `user/website/index.php` (or appropriate main page)
  - Rewrite public pages similarly:
    - `/` → `public/index.php`
    - `/privacy-policy` → `public/privacy_policy.php`
    - `/terms-and-conditions` → `public/terms_conditions.php`
- Ensure that:
  - Direct `.php` access is either redirected to the clean URL or allowed temporarily for backward compatibility based on your preference.
  - Old role-specific URLs (`/customer/dashboard`, `/franchisee/dashboard`, `/team/dashboard`) are redirected to `/dashboard` to avoid breaking existing bookmarks/links.

#### Phase 7: Cleanup (After Full Testing)
- Remove:
  - `assets/css - Copy/`, `franchisee - Copy.php`, `index.html_old`, and any clearly identified duplicate/test files (move tests into `tests/` folder).
  - Old `customer/`, `franchisee/`, `team/` folders once the `user/` area is fully verified and live.
  - Duplicate payment and config files replaced by centralized ones.
- Ensure all `.htaccess` rules, includes, and paths are consistent with the new structure.

### 4. URL Design Summary (No `.php` in URL)
- **User area**:
  - `/dashboard` → `user/dashboard/index.php`
  - `/kit` → `user/kit/index.php`
  - `/website` → `user/website/index.php` (or main website builder page)
  - `/profile` → `user/profile/index.php`
  - `/collaboration` → `user/collaboration/index.php`
  - `/referral` → `user/referral/index.php`
  - `/verification` → `user/verification/index.php`
  - `/teams` → `user/teams/index.php`
  - `/wallet` → `user/wallet/index.php`
- **Public pages**:
  - `/` → `public/index.php`
  - `/privacy-policy` → `public/privacy_policy.php`
  - `/terms-and-conditions` → `public/terms_conditions.php`
  - Other static pages/landing pages get similar clean slugs.
- **Payment**:
  - `/pay` → `payment_page/pay.php`
  - `/payment/verify` → `payment_page/verify.php`
  - `/payment/callback` → `payment_page/callback.php`

### 5. Constraints and Guarantees
- **No change to design/HTML markup** unless required for path updates (CSS/JS/image URLs).
- **No breaking of existing logic intentionally**; behavior should remain the same, only organized differently.
- **Step-by-step migration** with old folders kept until the new structure is fully tested.
- **Backups required** before removing any old folders or files.

---

If you confirm this plan, the next steps will be:
1. Create the new folders and base files (`app/config`, `app/helpers`, `user/includes`, etc.).
2. Implement `.htaccess` rules for clean URLs.
3. Start moving/mapping specific sections (dashboard, kit, website, payments) into the new structure in phases, verifying functionality at each step.

