<?php
// =============================================
// AMII - Regional Hype Data
// File: api/cities.php
// Mengambil data regional yang sudah dihitung
// oleh trending.php (AniList × BPS 2023)
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

// Panggil trending.php dan ambil bagian regional-nya
// Ini menghindari duplikasi fetch ke AniList
$trendingUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . str_replace('cities.php', 'trending.php', $_SERVER['REQUEST_URI']);

$ch = curl_init($trendingUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!$data || $data['status'] !== 'ok' || empty($data['regional'])) {
    // Fallback statis jika trending.php gagal
    echo json_encode([
        'status' => 'ok',
        'source' => 'fallback',
        'data'   => [
            ['name'=>'Jawa',          'hype_index'=>94, 'weight'=>1.00, 'info'=>'Pusat komunitas anime terbesar di Indonesia. Hub untuk retail merchandise, event cosplay, dan konten kreator.', 'top_titles'=>[], 'activity_count'=>0],
            ['name'=>'Sumatra',       'hype_index'=>78, 'weight'=>0.80, 'info'=>'Pasar berkembang dengan fokus pada streaming digital dan komunitas online.', 'top_titles'=>[], 'activity_count'=>0],
            ['name'=>'Sulawesi',      'hype_index'=>70, 'weight'=>0.72, 'info'=>'Community-driven market. Event lokal dan fan club aktif.', 'top_titles'=>[], 'activity_count'=>0],
            ['name'=>'Bali & NTT',    'hype_index'=>82, 'weight'=>0.84, 'info'=>'Pasar unik dengan pengaruh wisata & ekspat. Minat tinggi pada merchandise premium.', 'top_titles'=>[], 'activity_count'=>0],
            ['name'=>'Kalimantan',    'hype_index'=>65, 'weight'=>0.67, 'info'=>'Segmen emerging. Dominasi judul mainstream shonen.', 'top_titles'=>[], 'activity_count'=>0],
            ['name'=>'Papua & Maluku','hype_index'=>55, 'weight'=>0.57, 'info'=>'Pasar awal dengan infrastruktur digital yang terus berkembang.', 'top_titles'=>[], 'activity_count'=>0],
        ]
    ]);
    exit();
}

echo json_encode([
    'status'      => 'ok',
    'source'      => 'AniList API × BPS SUSENAS 2023',
    'synced_at'   => $data['synced_at'] ?? date('Y-m-d H:i:s'),
    'data'        => $data['regional'],
]);