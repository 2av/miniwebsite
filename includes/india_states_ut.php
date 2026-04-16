<?php
/**
 * Indian states and union territories for validating "Selected Cities / States" text
 * when business country is India. Single tokens are treated as city names (not verified).
 */
if (!function_exists('mw_india_state_lookup')) {
    function mw_india_state_names()
    {
        return [
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
            'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
            'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
            'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
            'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
            'Andaman and Nicobar Islands', 'Chandigarh',
            'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir',
            'Ladakh', 'Lakshadweep', 'Puducherry',
        ];
    }

    function mw_india_state_lookup()
    {
        static $lookup = null;
        if ($lookup !== null) {
            return $lookup;
        }
        $lookup = [];
        foreach (mw_india_state_names() as $n) {
            $lookup[mb_strtolower($n)] = true;
        }
        // Common aliases
        $aliases = [
            'orissa' => true,
            'daman and diu' => true,
            'dadra and nagar haveli' => true,
            'jammu & kashmir' => true,
            'pondicherry' => true,
        ];
        $lookup = array_merge($lookup, $aliases);
        return $lookup;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    function mw_validate_operation_locations_for_india($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return ['ok' => false, 'message' => 'Please enter the cities or states you serve.'];
        }
        $lookup = mw_india_state_lookup();
        $parts = preg_split('/\s*,\s*/u', $text);
        $non_empty_count = 0;
        foreach ($parts as $p) {
            if (trim($p) !== '') {
                $non_empty_count++;
            }
        }
        if ($non_empty_count > 6) {
            return [
                'ok' => false,
                'message' => 'You can choose up to 6 cities or states of India where you serve.',
            ];
        }
        $invalid = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (isset($lookup[mb_strtolower($p)])) {
                continue;
            }
            if (preg_match('/^(.+?)\s*[\-,]\s*(.+)$/u', $p, $m)) {
                $st = trim($m[2]);
                if (isset($lookup[mb_strtolower($st)])) {
                    continue;
                }
                $invalid[] = $p;
                continue;
            }
            // Single token: treat as city name (not in our list)
        }
        if (!empty($invalid)) {
            $show = implode('; ', array_slice($invalid, 0, 6));
            return [
                'ok' => false,
                'message' => 'Use a recognised Indian state/UT name, or "City, State" (e.g. Mumbai, Maharashtra). Check: ' . $show,
            ];
        }
        return ['ok' => true, 'message' => ''];
    }
}
