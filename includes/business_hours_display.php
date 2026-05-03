<?php
/**
 * Normalize d_business_hours JSON for public card display.
 * Supports v2 weekly schedule or legacy list of { days, hours } rows.
 */

if (!function_exists('mw_bh_format_time_ampm')) {
    function mw_bh_format_time_ampm($hhmm)
    {
        if ($hhmm === '' || $hhmm === null) {
            return '';
        }
        $hhmm = trim((string) $hhmm);
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
            return $hhmm;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return $hhmm;
        }
        $ap = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }

        return sprintf('%d:%02d %s', $h12, $min, $ap);
    }

    function mw_bh_day_signature($schedule, $dk)
    {
        if (empty($schedule[$dk]) || empty($schedule[$dk]['open'])) {
            return 'closed';
        }
        $ot = isset($schedule[$dk]['open_time']) ? trim((string) $schedule[$dk]['open_time']) : '';
        $ct = isset($schedule[$dk]['close_time']) ? trim((string) $schedule[$dk]['close_time']) : '';
        if ($ot === '' || $ct === '' || !preg_match('/^\d{2}:\d{2}$/', $ot) || !preg_match('/^\d{2}:\d{2}$/', $ct)) {
            return 'closed';
        }

        return 'open|' . $ot . '|' . $ct;
    }

    function mw_weekly_schedule_to_display_rows($schedule)
    {
        if (!is_array($schedule)) {
            return [];
        }
        $order = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $dayLabels = [
            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
            'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
        ];
        $rows = [];
        $i = 0;
        while ($i < 7) {
            $sig = mw_bh_day_signature($schedule, $order[$i]);
            $j = $i;
            while ($j < 7 && mw_bh_day_signature($schedule, $order[$j]) === $sig) {
                $j++;
            }
            $startLabel = $dayLabels[$order[$i]];
            $endLabel = $dayLabels[$order[$j - 1]];
            $daysStr = ($i === $j - 1) ? $startLabel : ($startLabel . ' - ' . $endLabel);
            if ($sig === 'closed') {
                $hoursStr = 'Closed';
            } else {
                $parts = explode('|', $sig);
                $ot = $parts[1] ?? '';
                $ct = $parts[2] ?? '';
                $hoursStr = mw_bh_format_time_ampm($ot) . ' - ' . mw_bh_format_time_ampm($ct);
            }
            $rows[] = ['days' => $daysStr, 'hours' => $hoursStr];
            $i = $j;
        }

        return $rows;
    }

    function mw_normalize_business_hours_display($json_raw)
    {
        $default = [
            ['days' => 'Monday - Thursday', 'hours' => '10:00 AM - 10:00 PM'],
            ['days' => 'Friday - Saturday', 'hours' => '10:00 AM - 12:00 AM'],
            ['days' => 'Sunday', 'hours' => 'Closed'],
        ];
        if ($json_raw === null || $json_raw === '') {
            return $default;
        }
        $decoded = json_decode($json_raw, true);
        if (!is_array($decoded)) {
            return $default;
        }
        if (isset($decoded['version']) && (int) $decoded['version'] === 2 && !empty($decoded['schedule']) && is_array($decoded['schedule'])) {
            return mw_weekly_schedule_to_display_rows($decoded['schedule']);
        }
        if (isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('days', $decoded[0])) {
            return $decoded;
        }

        return $default;
    }
}
