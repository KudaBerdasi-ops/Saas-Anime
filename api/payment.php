<?php
// =============================================
// AMII - Midtrans Payment Gateway
// File: api/payment.php
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── KONFIGURASI MIDTRANS ──────────────────────
// Ganti dengan key asli kamu dari https://dashboard.midtrans.com
define('MIDTRANS_SERVER_KEY', 'Mid-server-j4vm7a3h3uiMl4Ly0MDWUXX8');
define('MIDTRANS_IS_PRODUCTION', false);
define('MIDTRANS_IS_SANITIZED', true);
define('MIDTRANS_IS_3DS', true);

// URL Midtrans API
$midtrans_url = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

// ── HARGA PER PLAN ────────────────────────────
$plan_prices = [
    'pro'        => 500000,  // Rp 500.000/bulan
    'enterprise' => 0,       // Hubungi sales (tidak diproses di sini)
    'explorer'   => 0,       // Gratis
];

// ── TERIMA INPUT ──────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request body.']);
    exit();
}

$name    = htmlspecialchars(trim($input['name']    ?? ''));
$email   = htmlspecialchars(trim($input['email']   ?? ''));
$company = htmlspecialchars(trim($input['company'] ?? ''));
$plan    = trim($input['plan'] ?? 'pro');

// Validasi input
if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Nama dan email wajib diisi dengan benar.']);
    exit();
}

// Validasi plan
if (!isset($plan_prices[$plan])) {
    echo json_encode(['status' => 'error', 'message' => 'Plan tidak valid.']);
    exit();
}

// Plan gratis → tidak perlu payment
if ($plan_prices[$plan] === 0) {
    // Simpan ke waitlist saja
    $waitlist_data = ['name' => $name, 'email' => $email, 'company' => $company, 'plan' => $plan, 'created_at' => date('Y-m-d H:i:s')];
    file_put_contents(__DIR__ . '/waitlist_log.json', json_encode($waitlist_data) . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'free', 'message' => 'Pendaftaran berhasil! Tim kami akan menghubungi kamu.']);
    exit();
}

// ── BUAT ORDER ID UNIK ────────────────────────
$order_id = 'AMII-' . strtoupper($plan) . '-' . time() . '-' . rand(100, 999);

// ── PAYLOAD MIDTRANS ──────────────────────────
$payload = [
    'transaction_details' => [
        'order_id'     => $order_id,
        'gross_amount' => $plan_prices[$plan],
    ],
    'customer_details' => [
        'first_name' => $name,
        'email'      => $email,
        'phone'      => $input['phone'] ?? '',
    ],
    'item_details' => [
        [
            'id'       => 'AMII-' . strtoupper($plan),
            'price'    => $plan_prices[$plan],
            'quantity' => 1,
            'name'     => 'AMII ' . ucfirst($plan) . ' Plan - 1 Bulan',
        ]
    ],
    'credit_card' => [
        'secure' => MIDTRANS_IS_3DS,
    ],
    // Callback URL setelah pembayaran selesai
    'callbacks' => [
        'finish' => 'https://DOMAIN-KAMU.com/payment-success.html', // Ganti dengan domain kamu
    ],
];

// ── KIRIM REQUEST KE MIDTRANS ─────────────────
$auth = base64_encode(MIDTRANS_SERVER_KEY . ':');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $midtrans_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Basic ' . $auth,
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal terhubung ke Midtrans: ' . $curl_error]);
    exit();
}

$result = json_decode($response, true);

if ($http_status === 201 && isset($result['token'])) {
    // Simpan log order ke file (opsional, bisa diganti ke database)
    $log = [
        'order_id'   => $order_id,
        'name'       => $name,
        'email'      => $email,
        'company'    => $company,
        'plan'       => $plan,
        'amount'     => $plan_prices[$plan],
        'snap_token' => $result['token'],
        'status'     => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents(__DIR__ . '/orders_log.json', json_encode($log) . PHP_EOL, FILE_APPEND);

    // Kirim email pending ke user (menunggu konfirmasi pembayaran)
    require_once __DIR__ . '/send_email.php';
    $pending_body = '
    <!DOCTYPE html><html><body style="font-family:\'Segoe UI\',sans-serif;background:#050508;padding:40px;">
    <table width="560" style="background:#12141c;border-radius:16px;border:1px solid rgba(255,255,255,0.08);padding:32px;margin:auto;">
      <tr><td>
        <div style="text-align:center;font-size:48px;margin-bottom:16px;">⏳</div>
        <h2 style="color:#fff;text-align:center;margin:0 0 8px;">Menunggu Pembayaran</h2>
        <p style="color:#94a3b8;text-align:center;font-size:14px;margin:0 0 28px;">
          Hei <strong style="color:#fff;">' . $name . '</strong>, pesanan kamu sudah dibuat!<br>
          Selesaikan pembayaran untuk mengaktifkan akses <strong style="color:#a78bfa;">Market Pro</strong>.
        </p>
        <table width="100%" style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);border-radius:12px;padding:20px;">
          <tr><td style="color:#64748b;font-size:13px;padding:6px 0;">Order ID</td>
              <td style="color:#a78bfa;font-size:12px;font-family:monospace;text-align:right;">' . $order_id . '</td></tr>
          <tr><td style="color:#64748b;font-size:13px;padding:6px 0;">Total</td>
              <td style="color:#10b981;font-size:14px;font-weight:700;text-align:right;">Rp 500.000</td></tr>
          <tr><td style="color:#64748b;font-size:13px;padding:6px 0;">Status</td>
              <td style="text-align:right;"><span style="background:rgba(245,158,11,0.15);color:#f59e0b;font-size:11px;padding:3px 10px;border-radius:100px;">⏳ MENUNGGU</span></td></tr>
        </table>
        <p style="color:#475569;font-size:12px;text-align:center;margin:24px 0 0;">
          Email konfirmasi akan dikirim otomatis setelah pembayaran selesai.<br>
          © ' . date('Y') . ' AMII.AI
        </p>
      </td></tr>
    </table>
    </body></html>';
    sendEmail($email, '⏳ Pesanan AMII Market Pro Menunggu Pembayaran', $pending_body);

    // Kembalikan snap_token ke frontend
    echo json_encode([
        'status'       => 'ok',
        'snap_token'   => $result['token'],
        'order_id'     => $order_id,
        'redirect_url' => $result['redirect_url'] ?? '',
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => $result['error_messages'][0] ?? 'Gagal membuat transaksi Midtrans.',
        'detail'  => $result,
    ]);
}