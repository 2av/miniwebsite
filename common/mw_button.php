<?php
/**
 * Mini Website — common buttons (design system)
 *
 * Variants: back | save | next | primary | secondary | accent | cancel | danger |
 *           success | warning | info | outline-primary | outline-secondary |
 *           outline-success | outline-danger | outline-warning | outline-info
 *
 * Single button:
 *   echo mw_button([
 *       'variant' => 'save',
 *       'label'   => 'Save',
 *       'type'    => 'submit',
 *       'name'    => 'process1',
 *       'class'   => 'save_btn',
 *       'img'     => '../../assets/images/Save.png',
 *   ]);
 *
 * Link button:
 *   echo mw_button(['tag' => 'a', 'variant' => 'back', 'label' => 'Back', 'href' => '../dashboard/', 'icon' => 'fa-angle-left', 'angle' => 'left']);
 *
 * Step row (Back / Save / Next):
 *   echo mw_button_row_step([
 *       'back' => ['href' => '../dashboard/', 'label' => 'Back'],
 *       'save' => ['type' => 'submit', 'name' => 'process2', 'label' => 'Save', 'class' => 'save_btn', 'img' => '../../assets/images/Save.png'],
 *       'next' => ['href' => 'select-theme.php', 'label' => 'Next'],
 *   ]);
 */

if (!function_exists('mw_btn_variants')) {
    function mw_btn_variants() {
        return [
            'back', 'save', 'next', 'primary', 'secondary', 'accent', 'cancel',
            'danger', 'success', 'warning', 'info',
            'outline-primary', 'outline-secondary', 'outline-success',
            'outline-danger', 'outline-warning', 'outline-info',
        ];
    }
}

if (!function_exists('mw_btn_class')) {
    /**
     * Build a .mw-btn class string for a variant (+ optional extras).
     */
    function mw_btn_class($variant, $extra = '') {
        $variant = strtolower(trim((string) $variant));
        if ($variant === 'primary') {
            $variant = 'save';
        }
        if (!in_array($variant, mw_btn_variants(), true)) {
            $variant = 'secondary';
        }
        $classes = ['mw-btn', 'mw-btn-' . $variant];
        $extra = trim((string) $extra);
        if ($extra !== '') {
            $classes[] = $extra;
        }
        return implode(' ', $classes);
    }
}

if (!function_exists('mw_button')) {
    /**
     * Render one design-system button (<button> or <a>).
     *
     * @param array{
     *   tag?: 'button'|'a',
     *   variant?: string,
     *   label?: string,
     *   href?: string,
     *   type?: string,
     *   name?: string,
     *   value?: string,
     *   icon?: string,
     *   img?: string,
     *   img_alt?: string,
     *   angle?: 'left'|'right',
     *   class?: string,
     *   attrs?: string,
     *   disabled?: bool,
     *   id?: string,
     * } $options
     */
    function mw_button(array $options) {
        $tag      = strtolower($options['tag'] ?? 'button');
        $tag      = ($tag === 'a') ? 'a' : 'button';
        $variant  = $options['variant'] ?? 'secondary';
        $label    = (string) ($options['label'] ?? 'Button');
        $extra    = trim($options['class'] ?? '');
        $class    = htmlspecialchars(mw_btn_class($variant, $extra), ENT_QUOTES, 'UTF-8');
        $attrs    = trim($options['attrs'] ?? '');
        $disabled = !empty($options['disabled']);
        $id       = trim($options['id'] ?? '');
        $icon     = trim($options['icon'] ?? '');
        $img      = trim($options['img'] ?? '');
        $imgAlt   = htmlspecialchars($options['img_alt'] ?? '', ENT_QUOTES, 'UTF-8');
        $angle    = $options['angle'] ?? null;

        $inner = '';
        if ($angle === 'left' && $icon !== '') {
            $inner .= '<span class="mw-btn-angle" aria-hidden="true"><i class="fa ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i></span>';
        }
        if ($img !== '') {
            $inner .= '<img src="' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . '" alt="' . $imgAlt . '" class="mw-btn-img">';
        } elseif ($icon !== '' && $angle !== 'left' && $angle !== 'right') {
            $inner .= '<i class="fa ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> ';
        }
        $inner .= '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($angle === 'right' && $icon !== '') {
            $inner .= '<span class="mw-btn-angle" aria-hidden="true"><i class="fa ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i></span>';
        }

        $attrParts = [];
        if ($id !== '') {
            $attrParts[] = 'id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($tag === 'a') {
            $href = $options['href'] ?? '#';
            $attrParts[] = 'href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
        } else {
            $type = $options['type'] ?? 'button';
            $attrParts[] = 'type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"';
            if (!empty($options['name'])) {
                $attrParts[] = 'name="' . htmlspecialchars((string) $options['name'], ENT_QUOTES, 'UTF-8') . '"';
            }
            if (isset($options['value'])) {
                $attrParts[] = 'value="' . htmlspecialchars((string) $options['value'], ENT_QUOTES, 'UTF-8') . '"';
            }
            if ($disabled) {
                $attrParts[] = 'disabled';
                $attrParts[] = 'aria-disabled="true"';
            }
        }
        if ($attrs !== '') {
            $attrParts[] = $attrs;
        }

        return '<' . $tag . ' class="' . $class . '" ' . implode(' ', $attrParts) . '>' . $inner . '</' . $tag . '>';
    }
}

if (!function_exists('mw_button_row')) {
    /**
     * Render a row of buttons (raw HTML array or mw_button options).
     *
     * @param array<int, string|array<string, mixed>> $buttons
     * @param string $extraClass
     */
    function mw_button_row(array $buttons, $extraClass = '') {
        $class = trim('mw-btn-row ' . $extraClass);
        $html  = '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($buttons as $btn) {
            if (is_array($btn)) {
                $html .= mw_button($btn);
            } else {
                $html .= $btn;
            }
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('mw_button_row_step')) {
    /**
     * Website-builder step nav: Back (left) · Save (center) · Next (right).
     *
     * @param array{back?: array, save?: array|null, next?: array} $slots
     */
    function mw_button_row_step(array $slots) {
        $buttons = [];
        if (!empty($slots['back']) && is_array($slots['back'])) {
            $back = $slots['back'];
            $back['tag'] = 'a';
            $back['variant'] = $back['variant'] ?? 'back';
            $back['icon'] = $back['icon'] ?? 'fa-angle-left';
            $back['angle'] = 'left';
            $buttons[] = $back;
        }
        if (array_key_exists('save', $slots) && $slots['save'] !== null && is_array($slots['save'])) {
            $save = $slots['save'];
            $save['variant'] = $save['variant'] ?? 'save';
            $buttons[] = $save;
        }
        if (!empty($slots['next']) && is_array($slots['next'])) {
            $next = $slots['next'];
            $next['tag'] = 'a';
            $next['variant'] = $next['variant'] ?? 'next';
            $next['icon'] = $next['icon'] ?? 'fa-angle-right';
            $next['angle'] = 'right';
            $buttons[] = $next;
        }
        return mw_button_row($buttons);
    }
}
