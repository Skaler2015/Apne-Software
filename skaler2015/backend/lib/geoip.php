<?php
// ============================================================
// GeoIP lookup helper
// Uses the free ip-api.com endpoint (no API key needed for
// reasonable, non-commercial volume — about 45 requests/minute).
// Results are cached in the geoip_cache table so the same
// visitor IP is never looked up twice.
// ============================================================

function is_private_ip($ip) {
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function get_geoip($pdo, $ip) {
    $result = ['country' => null, 'region' => null, 'city' => null];

    if (!$pdo || empty($ip) || is_private_ip($ip)) {
        return $result;
    }

    // 1. Check cache first
    try {
        $stmt = $pdo->prepare("SELECT country, region, city FROM geoip_cache WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $cached = $stmt->fetch();
        if ($cached) {
            return $cached;
        }
    } catch (Exception $e) {
        error_log('GeoIP cache read failed: ' . $e->getMessage());
    }

    // 2. Not cached — call the free GeoIP API with a short timeout so we never slow the page down
    $url = "http://ip-api.com/json/" . urlencode($ip) . "?fields=status,country,regionName,city";
    $context = stream_context_create([
        'http' => ['timeout' => 2, 'ignore_errors' => true]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if (is_array($data) && ($data['status'] ?? '') === 'success') {
            $result['country'] = $data['country'] ?? null;
            $result['region'] = $data['regionName'] ?? null;
            $result['city'] = $data['city'] ?? null;
        }
    }

    // 3. Save to cache (even if lookup failed, so we don't retry a bad/unreachable IP every time)
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO geoip_cache (ip_address, country, region, city) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE country = VALUES(country), region = VALUES(region), city = VALUES(city)"
        );
        $stmt->execute([$ip, $result['country'], $result['region'], $result['city']]);
    } catch (Exception $e) {
        error_log('GeoIP cache write failed: ' . $e->getMessage());
    }

    return $result;
}

function get_visitor_ip() {
    // Hostinger sometimes sits behind a proxy/CDN — check common forwarded headers first
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
