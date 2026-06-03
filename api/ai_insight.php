<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$type = $body['type'] ?? '';
$data = $body['data'] ?? [];

// =============================================
// KONFIGURASI GROQ API
// =============================================
require_once __DIR__ . '/load_env.php';
define('GROQ_API_KEY', $_ENV['GROQ_API_KEY'] ?? '');
define('GROQ_MODEL',   'llama-3.1-8b-instant'); // Cepat & gratis

// =============================================
// CORE: Panggil Groq API (OpenAI-compatible)
// =============================================
function callGroq(string $system, string $user): string {
    $payload = json_encode([
        'model'       => GROQ_MODEL,
        'max_tokens'  => 1000,
        'temperature' => 0.7,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ]
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("cURL Error: " . $err);
    if ($httpCode !== 200) throw new Exception("Groq API error: HTTP $httpCode - " . $response);

    $result = json_decode($response, true);
    $text   = $result['choices'][0]['message']['content'] ?? '';
    if (empty($text)) throw new Exception("Format balasan AI tidak terduga.");
    return $text;
}

// Bersihkan response dan parse JSON
function parseAIJson(string $text): mixed {
    $text = trim($text);
    $text = preg_replace('/^```json\s*/m', '', $text);
    $text = preg_replace('/^```\s*/m', '', $text);
    $text = trim($text);
    // Ambil JSON dari dalam teks jika ada teks di luar
    if (!str_starts_with($text, '{') && !str_starts_with($text, '[')) {
        preg_match('/(\{.*\}|\[.*\])/s', $text, $matches);
        $text = $matches[0] ?? $text;
    }
    return json_decode($text, true);
}

// =============================================
// SYSTEM PROMPT
// =============================================
$SYSTEM = "Kamu adalah AMII Intelligence — AI analis pasar anime khusus Indonesia. " .
    "Kamu ahli dalam: tren anime global & lokal, perilaku fan Indonesia, strategi bisnis merchandise & retail anime, " .
    "dan dinamika komunitas anime di kota-kota besar Indonesia. " .
    "Gunakan bahasa Indonesia yang natural, padat, dan actionable untuk pelaku bisnis. " .
    "WAJIB: Kembalikan response HANYA dalam format JSON valid, tanpa teks atau markdown di luar JSON.";

try {
    switch ($type) {

        // =============================================
        // 1. AI TREND ALERTS
        // =============================================
        case 'alerts':
            $animeJson = json_encode(array_slice($data, 0, 8), JSON_UNESCAPED_UNICODE);
            $raw = callGroq($SYSTEM,
                "Data anime trending Indonesia saat ini:\n$animeJson\n\n" .
                "Buat 3 trend alert paling penting untuk pelaku bisnis anime Indonesia. " .
                "Fokus pada: konteks mengapa trending, sinyal yang terdeteksi, dan implikasi bisnis konkret.\n\n" .
                "Return HANYA JSON array (tanpa teks lain):\n" .
                '[{"title":"nama anime","status":"high|rising|stable","msg":"insight bisnis 1-2 kalimat"}]'
            );
            $result = parseAIJson($raw);
            if (!is_array($result)) throw new Exception('Format alerts tidak valid');
            echo json_encode(['status' => 'ok', 'data' => $result]);
            break;

        // =============================================
        // 2. AI MARKET BRIEF
        // =============================================
        case 'brief':
            $animeJson = json_encode(array_slice($data, 0, 10), JSON_UNESCAPED_UNICODE);
            $raw = callGroq($SYSTEM,
                "Data pasar anime Indonesia hari ini:\n$animeJson\n\n" .
                "Tulis Market Intelligence Brief untuk pelaku bisnis anime Indonesia.\n\n" .
                "Return HANYA JSON object (tanpa teks lain):\n" .
                '{"headline":"judul max 10 kata","summary":"kondisi pasar 2-3 kalimat","opportunity":"1 peluang bisnis konkret","warning":"1 hal yang perlu diwaspadai","mood":"bullish|neutral|bearish"}'
            );
            $result = parseAIJson($raw);
            if (!is_array($result)) throw new Exception('Format brief tidak valid');
            echo json_encode(['status' => 'ok', 'data' => $result]);
            break;

        // =============================================
        // 3. AI CITY INTELLIGENCE
        // =============================================
        case 'city':
            $cityName    = $data['city']    ?? 'Jakarta';
            $cityIndex   = $data['val']     ?? 80;
            $trendingJson = json_encode(array_slice($data['trending'] ?? [], 0, 5), JSON_UNESCAPED_UNICODE);

            $raw = callGroq($SYSTEM,
                "Kota: $cityName (Hype Index: $cityIndex/100)\n" .
                "Anime trending saat ini: $trendingJson\n\n" .
                "Berikan city intelligence spesifik untuk bisnis anime di $cityName.\n\n" .
                "Return HANYA JSON object (tanpa teks lain):\n" .
                '{"profile":"profil singkat fans anime di kota ini","top_genre":"genre paling dominan","strategy":"strategi bisnis paling efektif","best_title":"judul paling cocok","reason":"alasan konkret"}'
            );
            $result = parseAIJson($raw);
            if (!is_array($result)) throw new Exception('Format city insight tidak valid');
            echo json_encode(['status' => 'ok', 'data' => $result]);
            break;

        // =============================================
        // 4. AI BREAKOUT DETECTOR
        // =============================================
        case 'breakout':
            $animeJson = json_encode($data, JSON_UNESCAPED_UNICODE);
            $raw = callGroq($SYSTEM,
                "Data anime trending Indonesia saat ini:\n$animeJson\n\n" .
                "Identifikasi 2-3 judul yang paling berpotensi breakout/viral dalam 1-2 minggu ke depan.\n\n" .
                "Return HANYA JSON array (tanpa teks lain):\n" .
                '[{"title":"nama anime","confidence":"high|medium","timeframe":"estimasi waktu breakout","signal":"sinyal utama 1 kalimat","action":"rekomendasi aksi bisnis konkret"}]'
            );
            $result = parseAIJson($raw);
            if (!is_array($result)) throw new Exception('Format breakout tidak valid');
            echo json_encode(['status' => 'ok', 'data' => $result]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Tipe tidak dikenal: $type"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}