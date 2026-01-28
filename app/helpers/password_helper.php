<?php
/**
 * Password Helper Functions
 *
 * Centralized password hashing + verification so the same logic is used everywhere.
 */

/**
 * Hash a plaintext password using PHP's current default algorithm.
 */
function mw_hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Verify a plaintext password against a stored hash.
 */
function mw_verify_password(string $plain, ?string $hash): bool {
    if (empty($hash)) return false;
    return password_verify($plain, $hash);
}

/**
 * Verify password using (password_hash column preferred), then (password column as hash),
 * and finally (legacy plain text comparison) for backward compatibility.
 */
function mw_verify_stored_password(string $plain, ?string $password_hash_col, ?string $password_col): bool {
    if (!empty($password_hash_col) && mw_verify_password($plain, $password_hash_col)) {
        return true;
    }
    // Some older code stores hash in password column
    if (!empty($password_col) && mw_verify_password($plain, $password_col)) {
        return true;
    }
    // Legacy fallback: plain text in password column
    if (!empty($password_col) && hash_equals((string)$password_col, $plain)) {
        return true;
    }
    return false;
}

