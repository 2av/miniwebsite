<?php
/**
 * Shared special-offer helpers (admin + public MW).
 */

if (!function_exists('mw_special_offer_end_moment_ts')) {
    /** Unix timestamp for offer end, or null. */
    function mw_special_offer_end_moment_ts($date_raw, $time_raw) {
        $raw = trim((string) $date_raw);
        if ($raw === '' || $raw === '0000-00-00' || strpos($raw, '0000-00-00') === 0) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[\sT]\d/', $raw)) {
            $norm = str_replace('T', ' ', $raw);
            $norm = preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/', '$1', $norm);
            $ts = strtotime($norm);
            return ($ts === false) ? null : $ts;
        }
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $dm)) {
            return null;
        }
        $dateOnly = $dm[1];
        $timeRaw = trim((string) $time_raw);
        if ($timeRaw !== '') {
            $timeRaw = preg_replace('/\.\d+$/', '', $timeRaw);
            if (preg_match('/^(\d{1,2}:\d{2}(?::\d{2})?)/', $timeRaw, $mm)) {
                $timeRaw = $mm[1];
            } else {
                $timeRaw = '';
            }
        }
        $combined = ($timeRaw !== '') ? ($dateOnly . ' ' . $timeRaw) : ($dateOnly . ' 23:59:59');
        $ts = strtotime($combined);
        if ($ts !== false) {
            return $ts;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $combined)
            ?: DateTimeImmutable::createFromFormat('Y-m-d G:i:s', $combined)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $combined)
            ?: DateTimeImmutable::createFromFormat('Y-m-d G:i', $combined);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->getTimestamp();
        }
        return null;
    }
}

if (!function_exists('mw_special_offer_end_dt_expired')) {
    function mw_special_offer_end_dt_expired($date_raw, $time_raw) {
        $endTs = mw_special_offer_end_moment_ts($date_raw, $time_raw);
        if ($endTs === null) {
            return false;
        }
        return $endTs < time();
    }
}

/** Max offer schedule span in seconds (30 days). */
function mw_special_offer_max_validity_seconds() {
    return 30 * 86400;
}

/**
 * Validate offer schedule including 30-day maximum validity window.
 *
 * @return string Empty if OK, else plain-text message for the user.
 */
function mw_special_offer_validate_dt($start_date, $end_date, $start_time, $end_time) {
    $sd = trim((string) $start_date);
    $ed = trim((string) $end_date);
    $st = trim((string) $start_time);
    $et = trim((string) $end_time);
    if ($sd === '' && $ed === '' && $st === '' && $et === '') {
        return '';
    }
    if (($sd !== '') !== ($st !== '')) {
        return 'Start Dt: enter both date and time, or leave both empty.';
    }
    if (($ed !== '') !== ($et !== '')) {
        return 'End Dt: enter both date and time, or leave both empty.';
    }
    $start_ok = ($sd !== '' && $st !== '');
    $end_ok = ($ed !== '' && $et !== '');
    if ($start_ok !== $end_ok) {
        return $start_ok
            ? 'End Dt: enter both date and time when Start Dt is set.'
            : 'Start Dt: enter both date and time when End Dt is set.';
    }
    if (!$start_ok) {
        return '';
    }
    $ts_s = strtotime($sd . ' ' . $st);
    $ts_e = strtotime($ed . ' ' . $et);
    if ($ts_s === false || $ts_e === false) {
        return 'Start Dt or End Dt is not a valid date or time.';
    }
    if ($ts_e < $ts_s) {
        return 'End Dt must be on or after Start Dt.';
    }
    if (($ts_e - $ts_s) > mw_special_offer_max_validity_seconds()) {
        return 'Offer validity cannot exceed 30 days.';
    }
    return '';
}

/**
 * Set status to Inactive for expired offers that are still Active (image file is not removed).
 */
function mw_special_offer_auto_inactivate_expired($connect, $card_id, $user_id = 0) {
    if (!$connect || $card_id === '' || $card_id === null) {
        return;
    }
    $card_id_esc = mysqli_real_escape_string($connect, (string) $card_id);
    $user_clause = ((int) $user_id > 0) ? ' AND user_id=' . (int) $user_id : '';
    $q = mysqli_query(
        $connect,
        "SELECT id, end_date, end_time FROM card_special_offers
         WHERE card_id='$card_id_esc' AND status='Active' $user_clause"
    );
    if (!$q) {
        return;
    }
    while ($row = mysqli_fetch_assoc($q)) {
        if (!mw_special_offer_end_dt_expired($row['end_date'] ?? '', $row['end_time'] ?? '')) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            mysqli_query($connect, "UPDATE card_special_offers SET status='Inactive' WHERE id=$id LIMIT 1");
        }
    }
}

/**
 * Whether an offer row should render on the public Mini Website (image kept after expiry).
 */
function mw_special_offer_visible_on_mw(array $offer) {
    $status = trim((string) ($offer['status'] ?? 'Active'));
    if ($status === 'Active') {
        return true;
    }
    if ($status === 'Inactive' && mw_special_offer_end_dt_expired($offer['end_date'] ?? '', $offer['end_time'] ?? '')) {
        return true;
    }
    return false;
}
