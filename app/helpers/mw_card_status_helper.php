<?php
/**
 * Mini Website card status display helpers.
 */

/**
 * Card was created under / linked to a franchisee (f_user_email on digi_card).
 */
function mw_card_is_franchise_linked(array $row) {
    $f = trim((string) ($row['f_user_email'] ?? ''));
    return strlen($f) >= 3;
}

/**
 * Resolve MW status badge for dashboard / admin tables.
 *
 * @return array{class: string, text: string, is_trial: bool}
 */
function mw_card_resolve_display_status(array $row) {
    if (($row['complimentary_enabled'] ?? '') === 'Yes') {
        return ['class' => 'bg-success', 'text' => 'Active', 'is_trial' => false];
    }

    if (($row['d_payment_status'] ?? '') === 'Success') {
        $validity = $row['validity_date'] ?? '';
        $is_expired = (!empty($validity) && $validity !== '0000-00-00 00:00:00')
            ? (strtotime($validity) < time())
            : false;
        if ($is_expired) {
            $exp_text = 'Expired';
            if (!empty($validity) && $validity !== '0000-00-00 00:00:00') {
                $exp_text .= ' on ' . date('d-m-Y', strtotime($validity));
            }
            return ['class' => 'bg-secondary lightGray', 'text' => $exp_text, 'is_trial' => false];
        }
        return ['class' => 'bg-success', 'text' => 'Active', 'is_trial' => false];
    }

    $uploaded = $row['uploaded_date'] ?? '';
    $uploaded_ts = (!empty($uploaded) && $uploaded !== '0000-00-00 00:00:00') ? strtotime($uploaded) : 0;
    $trial_end_ts = $uploaded_ts > 0 ? strtotime('+7 days', $uploaded_ts) : 0;
    $trial_expired = ($trial_end_ts > 0 && $trial_end_ts < time());

    if (mw_card_is_franchise_linked($row)) {
        if ($trial_expired) {
            return ['class' => 'bg-secondary lightGray', 'text' => 'Inactive', 'is_trial' => false];
        }
        return ['class' => 'bg-pending', 'text' => 'Pending Payment', 'is_trial' => false];
    }

    if ($trial_expired) {
        return ['class' => 'bg-secondary lightGray', 'text' => 'Inactive', 'is_trial' => false];
    }

    return ['class' => 'bg-pending', 'text' => '7 Day Trial', 'is_trial' => true];
}

/**
 * Short status label for referral / collaboration lists.
 */
function mw_card_resolve_short_status(array $row) {
    $resolved = mw_card_resolve_display_status($row);
    return $resolved['text'];
}
