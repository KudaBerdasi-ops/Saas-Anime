<?php
/**
 * AMII — Cities API (Regional Hype Map)
 * Return hype score per kota berdasarkan aktivitas real user
 *
 * GET /api/cities.php
 * GET /api/cities.php?city=Jakarta  → detail 1 kota + top titles
 */

require_once 'config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo  = getDB();
    $city = $_GET['city'] ?? null;

    if ($city) {
        // --- Detail 1 kota ---
        $stmt = $pdo->prepare("
            SELECT
                ch.name,
                ch.hype_index,
                ch.info,
                ch.weight,
                ch.activity_count,
                ch.top_titles,
                ch.updated_at,
                COUNT(DISTINCT ua.user_id) AS unique_users_30d,
                COUNT(ua.id)              AS total_activity_30d
            FROM city_hype ch
            LEFT JOIN user_activity ua
                ON ua.city = ch.name
               AND ua.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE ch.name = ?
            GROUP BY ch.id
        ");
        $stmt->execute([$city]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'City not found']);
            exit;
        }

        // Decode top_titles JSON
        $row['top_titles'] = json_decode($row['top_titles'] ?? '[]', true);

        echo json_encode(['status' => 'ok', 'data' => $row]);

    } else {
        // --- Semua kota ---
        // Ambil dari city_hype, enriched dengan activity 30 hari terakhir
        $stmt = $pdo->query("
            SELECT
                ch.name,
                ch.hype_index,
                ch.info,
                ch.weight,
                ch.activity_count,
                ch.top_titles,
                ch.updated_at
            FROM city_hype ch
            ORDER BY ch.hype_index DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode top_titles JSON untuk setiap kota
        foreach ($rows as &$row) {
            $row['top_titles'] = json_decode($row['top_titles'] ?? '[]', true);
        }

        // Jika belum ada data aktivitas sama sekali, kembalikan data default
        // supaya Regional Hype Map tetap tampil
        if (empty($rows)) {
            $rows = getDefaultCities();
        }

        echo json_encode(['status' => 'ok', 'data' => $rows]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// ============================================================
// FALLBACK: data default jika tabel masih kosong
// ============================================================
function getDefaultCities(): array {
    return [
        ['name' => 'Jakarta',    'hype_index' => 50, 'info' => 'Data sedang dikumpulkan...', 'weight' => 1.0,  'activity_count' => 0, 'top_titles' => []],
        ['name' => 'Yogyakarta', 'hype_index' => 45, 'info' => 'Data sedang dikumpulkan...', 'weight' => 0.94, 'activity_count' => 0, 'top_titles' => []],
        ['name' => 'Bali',       'hype_index' => 40, 'info' => 'Data sedang dikumpulkan...', 'weight' => 0.90, 'activity_count' => 0, 'top_titles' => []],
        ['name' => 'Bandung',    'hype_index' => 38, 'info' => 'Data sedang dikumpulkan...', 'weight' => 0.87, 'activity_count' => 0, 'top_titles' => []],
        ['name' => 'Surabaya',   'hype_index' => 35, 'info' => 'Data sedang dikumpulkan...', 'weight' => 0.80, 'activity_count' => 0, 'top_titles' => []],
        ['name' => 'Medan',      'hype_index' => 28, 'info' => 'Data sedang dikumpulkan...', 'weight' => 0.63, 'activity_count' => 0, 'top_titles' => []],
        ['name' => 'Makassar',   'hype_index' => 25, 'info' => 'Data sedang dikumpulkan...', 'weight' => 0.59, 'activity_count' => 0, 'top_titles' => []],
        ['name' => 'Semarang',   'hype_index' => 22, 'info' => 'Data sedang dikumpulkan...', 'weight' => 0.55, 'activity_count' => 0, 'top_titles' => []],
    ];
}