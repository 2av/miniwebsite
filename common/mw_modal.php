<?php
/**
 * Mini Website — common popup / modal (design system)
 *
 * Sizes: sm (384px) · md (512px, default) · lg (768px)
 *
 * PHP — render a modal in any page:
 *   require_once __DIR__ . '/../../common/mw_modal.php';
 * Styles: mw_modal_print_styles() (included from user/includes/header.php)
 *
 *   mw_modal_render([
 *       'id'       => 'myModal',
 *       'size'     => 'md',           // sm | md | lg
 *       'title'    => 'Quick Add Customer',
 *       'icon'     => 'fa-bolt',
 *       'body'     => mw_modal_callout('Quick action', 'Fill in the details below.') . '<p>...</p>',
 *       'footer' => mw_modal_footer([
 *           ['label' => 'Cancel', 'class' => 'mw-btn mw-btn-cancel', 'attrs' => 'data-mw-modal-close'],
 *           ['label' => 'Save',   'class' => 'mw-btn mw-btn-save', 'attrs' => 'type="submit" form="myForm"'],
 *       ]),
 *   ]);
 *
 * JS — open / close:
 *   MwModal.open('myModal');
 *   MwModal.open({ id: 'myModal', size: 'lg', title: '...', body: '...' });
 *   MwModal.close('myModal');
 *   MwModal.confirm({ title: 'Delete?', message: '...', size: 'sm', onConfirm: fn });
 *
 * HTML triggers (no JS required):
 *   <button type="button" data-mw-modal-open="myModal">Open</button>
 *   <button type="button" data-mw-modal-close>Close</button>
 */

if (!function_exists('mw_modal_normalize_size')) {
    function mw_modal_normalize_size($size) {
        $size = strtolower(trim((string) $size));
        return in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    }
}

if (!function_exists('mw_modal_footer')) {
    /**
     * Build footer button HTML for mw_modal_render().
     *
     * @param array<int, array{label: string, class?: string, attrs?: string}> $buttons
     */
    function mw_modal_footer(array $buttons) {
        $html = '';
        foreach ($buttons as $btn) {
            $label = htmlspecialchars($btn['label'] ?? 'Button', ENT_QUOTES, 'UTF-8');
            $class = htmlspecialchars($btn['class'] ?? 'mw-btn mw-btn-back', ENT_QUOTES, 'UTF-8');
            $attrs = $btn['attrs'] ?? 'type="button"';
            $html .= '<button class="' . $class . '" ' . $attrs . '>' . $label . '</button>';
        }
        return $html;
    }
}

if (!function_exists('mw_modal_print_styles')) {
    /**
     * Modal design system CSS (Quick Add–style blue gradient header, white body, amber CTA).
     * Included once from user/includes/header.php.
     */
    function mw_modal_print_styles() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
<style id="mw-modal-design">
:root {
    --mw-modal-header-bg-start: #1e4db7;
    --mw-modal-header-bg-end:   #153e9b;
    --mw-modal-header-bg:       linear-gradient(90deg, var(--mw-modal-header-bg-start) 0%, var(--mw-modal-header-bg-end) 100%);
    --mw-modal-header-text:     #ffffff;
    --mw-modal-header-icon-bg:  rgba(255, 255, 255, 0.22);
    --mw-modal-body-bg:         #ffffff;
    --mw-modal-surface-tint:    #f8fafc;
    --mw-modal-border:          #e2e8f0;
    --mw-modal-label:           #1a2b4b;
    --mw-modal-input-border:    #e2e8f0;
    --mw-modal-input-text:      #4a5678;
    --mw-modal-placeholder:     #8a94a6;
    --mw-modal-muted:           #8a94a6;
    --mw-modal-section:         #1e4db7;
    --mw-modal-accent:          #ffc107;
    --mw-modal-accent-hover:    #e6ac00;
    --mw-modal-callout-bg:      #f0f7ff;
    --mw-modal-callout-border:  #cfe4ff;
    --mw-modal-cancel-bg:       #ffffff;
    --mw-modal-cancel-border:   #e2e8f0;
    --mw-modal-cancel-text:     #4a5568;
    --mw-modal-radius:          14px;
}
body.mw-modal-open { overflow: hidden; }
.mw-modal {
    position: fixed;
    inset: 0;
    z-index: var(--mw-modal-z-index, 1055);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    box-sizing: border-box;
}
.mw-modal.is-open { display: flex; }
.mw-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgb(26 43 75 / 0.45);
    backdrop-filter: blur(2px);
}
.mw-modal-panel {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    width: calc(100% - 0.5rem);
    max-height: calc(100vh - 2rem);
    background: var(--mw-modal-body-bg);
    border: 1px solid var(--mw-modal-border);
    border-radius: var(--mw-modal-radius);
    box-shadow: 0 12px 40px -8px rgb(21 62 155 / 0.22), 0 4px 12px -2px rgb(0 0 0 / 0.08);
    overflow: hidden;
    animation: mw-modal-in 0.2s ease-out;
}
.mw-modal-panel.mw-modal-sm  { max-width: var(--mw-modal-width-sm, 24rem); }
.mw-modal-panel.mw-modal-md  { max-width: var(--mw-modal-width-md, 32rem); }
.mw-modal-panel.mw-modal-lg  { max-width: var(--mw-modal-width-lg, 48rem); }
.mw-modal-panel.mw-modal-xl  { max-width: var(--mw-modal-width-xl, 71.25rem); }
.mw-modal-light .mw-modal-panel,
.mw-modal-light.mw-modal-panel { background: var(--mw-color-surface, #fff); }
.mw-modal-light .mw-modal-body { background: var(--mw-color-surface, #fff); }
.mw-modal-light .mw-modal-footer {
    background: var(--mw-color-surface, #fff) !important;
    border-top: 1px solid var(--mw-modal-border) !important;
}
@keyframes mw-modal-in {
    from { opacity: 0; transform: translateY(0.75rem) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.mw-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 0;
    background: var(--mw-modal-header-bg);
    color: var(--mw-modal-header-text);
    flex-shrink: 0;
}
.mw-modal-header-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1 1 auto;
}
.mw-modal-header-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: var(--mw-modal-header-icon-bg);
    color: var(--mw-modal-header-text);
    font-size: 1rem;
    flex-shrink: 0;
}
.mw-modal-header-text-wrap { min-width: 0; }
.mw-modal-title {
    margin: 0;
    font-size: 23px !important;
    font-weight: 700;
    line-height: 1.3;
    color: var(--mw-modal-header-text);
}
.mw-modal-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    padding: 0;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--mw-modal-header-text);
    font-size: 23px;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
    opacity: 0.92;
    transition: opacity .15s, background .15s;
}
.mw-modal-close:hover {
    opacity: 1;
    background: rgb(255 255 255 / 0.12);
}
.mw-modal-close:focus { outline: none; box-shadow: 0 0 0 3px rgb(255 255 255 / 0.35); }
.mw-modal-body {
    padding: var(--mw-modal-padding-lg, 1.5rem);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    flex: 1 1 auto;
    min-height: 0;
    font-size: var(--mw-font-body, 0.875rem);
    color: var(--mw-color-text, #334155);
    background: var(--mw-modal-body-bg);
}
.mw-modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 0.875rem 1.25rem 1rem;
    border-top: 1px solid var(--mw-modal-border);
    background: var(--mw-modal-surface-tint);
    flex-shrink: 0;
}
.mw-modal-footer .mw-btn { min-width: 5.5rem; }
.mw-modal-footer .mw-btn-save,
.mw-modal-footer .mw-btn-accent,
.mw-modal-footer .btn.mw-btn-accent {
    background: var(--mw-modal-accent);
    border-color: var(--mw-modal-accent);
    color: #1a2b4b;
    font-weight: 600;
    border-radius: 8px;
}
.mw-modal-footer .mw-btn-save:hover,
.mw-modal-footer .mw-btn-accent:hover,
.mw-modal-footer .btn.mw-btn-accent:hover {
    background: var(--mw-modal-accent-hover);
    border-color: var(--mw-modal-accent-hover);
    color: #1a2b4b;
}
.mw-modal-footer .mw-btn-cancel,
.mw-modal-footer .btn.btn-light.border,
.mw-modal-footer .btn-followup-close {
    background: var(--mw-modal-cancel-bg);
    border: 1px solid var(--mw-modal-cancel-border) !important;
    color: var(--mw-modal-cancel-text);
    border-radius: 8px;
    min-width: 5rem;
    font-weight: 500;
}
.mw-modal-header .btn-add-followup-from-view,
.mw-modal-header .mw-btn-accent {
    background: var(--mw-modal-accent);
    border-color: var(--mw-modal-accent);
    color: #1a2b4b;
    font-weight: 600;
    border-radius: 8px;
    font-size: 13px;
}
.mw-modal-header .btn-add-followup-from-view:hover,
.mw-modal-header .mw-btn-accent:hover {
    background: var(--mw-modal-accent-hover);
    border-color: var(--mw-modal-accent-hover);
    color: #1a2b4b;
}
.mw-modal-callout {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    background: var(--mw-modal-callout-bg);
    border: 1px solid var(--mw-modal-callout-border);
    border-radius: 8px;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: var(--mw-modal-muted);
}
.mw-modal-callout-icon {
    color: var(--mw-modal-section);
    font-size: 1rem;
    margin-top: 0.1rem;
    flex-shrink: 0;
}
.mw-modal-callout-title {
    display: block;
    font-weight: 700;
    color: var(--mw-modal-section);
    margin-bottom: 0.15rem;
}
.mw-modal-section-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--mw-modal-section);
    margin-bottom: 0.5rem;
    border-bottom: 1px solid var(--mw-modal-border);
    padding-bottom: 0.4rem;
}
.mw-modal-expand-trigger {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    margin-top: 0.75rem;
    padding: 0.65rem 1rem;
    border: 1px dashed var(--mw-modal-callout-border);
    border-radius: 8px;
    background: var(--mw-modal-callout-bg);
    color: var(--mw-modal-section);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
.mw-modal-expand-trigger:hover {
    background: #e8f2ff;
    border-color: #9ec5ff;
    color: var(--mw-modal-section);
    text-decoration: none;
}
.mw-modal .form-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--mw-modal-label);
    margin-bottom: 0.35rem;
}
.mw-modal .form-label .text-danger,
.mw-modal .form-label .required-asterisk { color: #dc3545; }
.mw-modal .form-control {
    font-size: 0.8125rem;
    min-height: 40px;
    background: #fff;
    border: 1px solid var(--mw-modal-input-border);
    border-radius: 8px;
    color: var(--mw-modal-input-text);
}
.mw-modal .form-select {
    font-size: 0.8125rem;
    min-height: 40px;
    border: 1px solid var(--mw-modal-input-border);
    border-radius: 8px;
    color: var(--mw-modal-input-text);
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%238a94a6' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.7' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.65rem center;
    background-size: 14px 10px;
    padding-right: 2.25rem;
}
.mw-modal .form-control::placeholder { color: var(--mw-modal-placeholder); }
.mw-followup-history-table {
    margin-bottom: 0;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}
.mw-followup-history-table thead th {
    background: var(--mw-modal-header-bg-start);
    color: var(--mw-modal-header-text);
    font-size: 13px;
    font-weight: 600;
    border: none;
    padding: 10px 12px;
}
.mw-followup-history-table tbody td {
    font-size: 13px;
    color: var(--mw-modal-input-text);
    padding: 10px 12px;
    vertical-align: middle;
    background: #fff;
    border-color: var(--mw-modal-border);
}
.mw-followup-history-table tbody tr:nth-child(even) td { background: #f8faff; }
.mw-followup-empty-text { color: var(--mw-modal-muted); font-size: 13px; }
.mw-modal-action-title { display: flex; align-items: center; gap: 8px; }
.mw-modal-action-title .fa-whatsapp { color: #25d366; font-size: 18px; }
.mw-modal-footer.wa-popup-footer {
    background: var(--mw-color-surface, #fff) !important;
    border-top: 1px solid var(--mw-modal-border) !important;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}
.mw-modal-footer.wa-popup-footer .btn-success { font-weight: 600; }
.mw-modal-footer.wa-popup-footer .btn-outline-primary {
    border-color: #2d6adf;
    color: #2d6adf;
    font-weight: 600;
    background: #fff;
}
.mw-modal-footer.wa-popup-footer .btn-outline-primary:hover {
    background: #f0f6ff;
    border-color: #1f58c6;
    color: #1f58c6;
}
.mw-modal-footer.wa-popup-footer .btn-danger { font-weight: 600; }
@media (max-width: 767.98px) {
    .mw-modal .mw-modal-panel { margin: 0.5rem; }
    .mw-modal .form-label,
    .mw-modal .form-control,
    .mw-modal .form-select { font-size: 13px; }
    .mw-modal .mw-modal-footer .btn { font-size: 13px; }
    .mw-modal:has(.mw-modal-xl).is-open {
        align-items: flex-start;
        padding-top: max(12px, env(safe-area-inset-top, 0px));
        padding-bottom: max(12px, env(safe-area-inset-bottom, 0px));
        padding-left: max(8px, env(safe-area-inset-left, 0px));
        padding-right: max(8px, env(safe-area-inset-right, 0px));
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }
    .mw-modal:has(.mw-modal-xl) .mw-modal-panel {
        max-width: calc(100% - 1rem);
        margin: 0 auto;
        min-height: 0;
        max-height: calc(100vh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px) - 40px);
        max-height: calc(100dvh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px) - 40px);
        display: flex;
        flex-direction: column;
    }
    .mw-modal-footer.wa-popup-footer {
        flex-direction: column;
        align-items: stretch !important;
    }
    .mw-modal-footer.wa-popup-footer .btn {
        width: 100%;
        min-height: 44px;
    }
}
.mw-modal-panel > form.mw-modal-form,
.mw-modal-panel > form {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1 1 auto;
    max-height: calc(100vh - 2rem);
}
</style>
        <?php
    }
}

if (!function_exists('mw_modal_callout')) {
    /**
     * Info callout box for modal body (Quick action style).
     */
    function mw_modal_callout($title, $message = '', $iconClass = 'fa-bolt') {
        $title = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');
        $icon = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $iconClass);
        if ($icon === '') {
            $icon = 'fa-bolt';
        }
        $iconHtml = '<i class="fa ' . $icon . ' mw-modal-callout-icon" aria-hidden="true"></i>';
        $body = '<strong class="mw-modal-callout-title">' . $title . '</strong>';
        if ($message !== '') {
            $body .= ' ' . $message;
        }
        return '<div class="mw-modal-callout" role="note">' . $iconHtml . '<div>' . $body . '</div></div>';
    }
}

if (!function_exists('mw_modal_render')) {
    /**
     * Output a reusable .mw-modal shell.
     *
     * @param array{
     *   id?: string,
     *   size?: 'sm'|'md'|'lg',
     *   title?: string,
     *   icon?: string,
     *   body?: string,
     *   footer?: string,
     *   closable?: bool,
     *   static?: bool,
     *   hidden?: bool,
     *   class?: string,
     *   body_class?: string,
     * } $options
     */
    function mw_modal_render(array $options) {
        $id        = preg_replace('/[^a-zA-Z0-9_-]/', '', $options['id'] ?? 'mwModal');
        if ($id === '') {
            $id = 'mwModal';
        }
        $size      = mw_modal_normalize_size($options['size'] ?? 'md');
        $title     = $options['title'] ?? '';
        $icon      = preg_replace('/[^a-zA-Z0-9_-]/', '', $options['icon'] ?? '');
        $body      = $options['body'] ?? '';
        $footer    = $options['footer'] ?? '';
        $closable  = !isset($options['closable']) || $options['closable'];
        $static    = !empty($options['static']);
        $hidden    = !isset($options['hidden']) || $options['hidden'];
        $extra     = trim($options['class'] ?? '');
        $bodyClass = trim($options['body_class'] ?? '');

        $titleId   = $id . 'Title';
        $hiddenAttr = $hidden ? ' hidden' : '';
        $staticAttr = $static ? ' data-mw-modal-static="true"' : '';
        $modalClass = trim('mw-modal ' . $extra);
        $panelClass = 'mw-modal-panel mw-modal-' . $size;
        ?>
<div class="<?php echo htmlspecialchars($modalClass, ENT_QUOTES, 'UTF-8'); ?>"
     id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>"
     role="dialog"
     aria-modal="true"
     <?php if ($title !== ''): ?>aria-labelledby="<?php echo htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
     <?php echo $hiddenAttr . $staticAttr; ?>>
    <div class="mw-modal-backdrop" data-mw-modal-close<?php echo $static ? ' data-mw-modal-static="true"' : ''; ?> aria-hidden="true"></div>
    <div class="<?php echo htmlspecialchars($panelClass, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($title !== '' || $closable): ?>
        <div class="mw-modal-header">
            <?php if ($title !== '' || $icon !== ''): ?>
            <div class="mw-modal-header-main">
                <?php if ($icon !== ''): ?>
                <span class="mw-modal-header-icon" aria-hidden="true"><i class="fa <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                <?php endif; ?>
                <div class="mw-modal-header-text-wrap">
                    <?php if ($title !== ''): ?>
                    <h2 class="mw-modal-title" id="<?php echo htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <span></span>
            <?php endif; ?>
            <?php if ($closable): ?>
            <button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="mw-modal-body<?php echo $bodyClass !== '' ? ' ' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') : ''; ?>">
            <?php echo $body; ?>
        </div>
        <?php if ($footer !== ''): ?>
        <div class="mw-modal-footer">
            <?php echo $footer; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
        <?php
    }
}
