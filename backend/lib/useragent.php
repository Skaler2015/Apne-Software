<?php
// ============================================================
// Lightweight User-Agent parser
// Detects browser, OS, and device type without any external
// library or API call — just pattern matching on the UA string.
// Not as precise as a full UA database, but accurate enough
// for analytics-level reporting.
// ============================================================

function parse_user_agent($ua) {
    $ua = $ua ?: '';
    return [
        'browser' => detect_browser($ua),
        'os' => detect_os($ua),
        'device_type' => detect_device_type($ua),
    ];
}

function detect_browser($ua) {
    if (stripos($ua, 'Edg/') !== false) return 'Edge';
    if (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) return 'Opera';
    if (stripos($ua, 'Chrome') !== false && stripos($ua, 'Chromium') === false) return 'Chrome';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    if (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) return 'Safari';
    if (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false) return 'Internet Explorer';
    return 'Other';
}

function detect_os($ua) {
    if (stripos($ua, 'Windows') !== false) return 'Windows';
    if (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) return 'macOS';
    if (stripos($ua, 'Android') !== false) return 'Android';
    if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iOS') !== false) return 'iOS';
    if (stripos($ua, 'Linux') !== false) return 'Linux';
    return 'Other';
}

function detect_device_type($ua) {
    if (stripos($ua, 'iPad') !== false || stripos($ua, 'Tablet') !== false ||
        (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)) {
        return 'tablet';
    }
    if (stripos($ua, 'Mobile') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'Android') !== false) {
        return 'mobile';
    }
    if (stripos($ua, 'Windows') !== false || stripos($ua, 'Macintosh') !== false || stripos($ua, 'Linux') !== false) {
        return 'desktop';
    }
    return 'other';
}

function detect_referrer_source($referrer) {
    if (empty($referrer)) return 'direct';
    $host = parse_url($referrer, PHP_URL_HOST) ?: '';
    if (stripos($host, 'google.') !== false) return 'google';
    if (stripos($host, 'bing.') !== false) return 'bing';
    if (stripos($host, 'yahoo.') !== false) return 'yahoo';
    if (stripos($host, 'duckduckgo.') !== false) return 'duckduckgo';
    if (preg_match('/facebook\.|instagram\.|twitter\.|x\.com|linkedin\.|whatsapp\.|t\.me|reddit\./i', $host)) return 'social';
    if (stripos($host, 'apnesoftware.com') !== false) return 'internal';
    return 'referral';
}
