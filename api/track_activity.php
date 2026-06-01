<?php
/**
 * AMII — Track User Activity + IP Geolocation
 * POST /api/track_activity.php
 * Body JSON: { "anime_title": "...", "action_type": "view|search|click" }
 */

require_once 'config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$anime_title = trim($input['anime_title'] ?? '');
$action_type = in_array($input['action_type'] ?? '', ['view','search','click'])
               ? $input['action_type'] : 'view';

if (empty($anime_title)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'anime_title required']);
    exit;
}

// Ambil IP user
$ip = $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_X_REAL_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '127.0.0.1';
$ip = trim(explode(',', $ip)[0]);

// Ambil session user jika ada
session_start();
$user_id = $_SESSION['user_id'] ?? null;

// Detect kota via IP
$city     = 'Unknown';
$province = 'Unknown';

$is_local = in_array($ip, ['127.0.0.1', '::1'])
    || str_starts_with($ip, '192.168.')
    || str_starts_with($ip, '10.')
    || str_starts_with($ip, '172.');

if (!$is_local) {
    $loc      = getLocationFromIP($ip);
    $city     = $loc['city'];
    $province = $loc['province'];
} else {
    // Development: pakai Jakarta sebagai default localhost
    $city     = 'Jakarta';
    $province = 'DKI Jakarta';
}

try {
    $pdo = getDB();

    // Catat aktivitas
    $stmt = $pdo->prepare("
        INSERT INTO user_activity (user_id, city, province, anime_title, action_type, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $city, $province, $anime_title, $action_type, $ip]);

    // Update city & IP di tabel users jika login
    if ($user_id) {
        $pdo->prepare("UPDATE users SET city = ?, province = ?, last_ip = ? WHERE id = ?")
            ->execute([$city, $province, $ip, $user_id]);
    }

    // Recalculate hype score untuk kota ini
    updateCityHype($pdo, $city);

    echo json_encode(['status' => 'ok', 'city' => $city, 'province' => $province]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// ============================================================

function getLocationFromIP(string $ip): array {
    $cache_file = sys_get_temp_dir() . '/ip_geo_' . md5($ip) . '.json';
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 86400) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached) return $cached;
    }

    $result = ['city' => 'Unknown', 'province' => 'Unknown'];
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $url = "http://ip-api.com/json/{$ip}?fields=status,city,regionName&lang=en";
    $response = @file_get_contents($url, false, $ctx);

    if ($response) {
        $data = json_decode($response, true);
        if (($data['status'] ?? '') === 'success') {
            $result = ['city' => $data['city'] ?? 'Unknown', 'province' => $data['regionName'] ?? 'Unknown'];
            file_put_contents($cache_file, json_encode($result));
        }
    }
    return $result;
}

function updateCityHype(PDO $pdo, string $city): void {
    // Hitung aktivitas kota ini dalam 30 hari
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_activity,
               COUNT(DISTINCT user_id) AS unique_users
        FROM user_activity
        WHERE city = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$city]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['total_activity'] == 0) return;

    // Max activity semua kota untuk normalisasi
    $max_row = $pdo->query("
        SELECT MAX(cnt) as max_cnt FROM (
            SELECT COUNT(*) AS cnt FROM user_activity
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY city
        ) AS sub
    ")->fetch(PDO::FETCH_ASSOC);
    $max_activity = max((int)($max_row['max_cnt'] ?? 1), 1);

    // Hype score 0-100, minimal 10
    $hype_score = max(10, min(100, (int)round(($row['total_activity'] / $max_activity) * 100)));

    // Top 3 anime di kota ini
    $top_stmt = $pdo->prepare("
        SELECT anime_title, COUNT(*) AS cnt
        FROM user_activity
        WHERE city = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY anime_title
        ORDER BY cnt DESC
        LIMIT 3
    ");
    $top_stmt->execute([$city]);
    $top_titles = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

    $info = "Berdasarkan {$row['total_activity']} aktivitas dari {$row['unique_users']} user unik dalam 30 hari terakhir.";

    // Cek apakah kota sudah ada
    $check = $pdo->prepare("SELECT id FROM city_hype WHERE name = ?");
    $check->execute([$city]);
    $exists = $check->fetch();

    if ($exists) {
        $pdo->prepare("
            UPDATE city_hype
            SET hype_index = ?, activity_count = ?, top_titles = ?, info = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ")->execute([$hype_score, $row['total_activity'], json_encode($top_titles), $info, $city]);
    } else {
        $pdo->prepare("
            INSERT INTO city_hype (name, hype_index, activity_count, top_titles, info, weight)
            VALUES (?, ?, ?, ?, ?, 0.50)
        ")->execute([$city, $hype_score, $row['total_activity'], json_encode($top_titles), $info]);
    }
}