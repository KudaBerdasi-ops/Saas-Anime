<?php
// =============================================
// AMII - Midtrans Webhook / Notification Handler
// File: api/payment_notify.php
// =============================================

header('Content-Type: application/json');

define('MIDTRANS_SERVER_KEY', 'Mid-server-j4vm7a3h3uiMl4Ly0MDWUXX8');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
    exit();
}

$order_id           = $input['order_id']           ?? '';
$status_code        = $input['status_code']        ?? '';
$gross_amount       = $input['gross_amount']       ?? '';
$signature_key      = $input['signature_key']      ?? '';
$transaction_status = $input['transaction_status'] ?? '';
$fraud_status       = $input['fraud_status']       ?? '';

// ── VERIFIKASI SIGNATURE ──────────────────────
$expected_signature = hash('sha512', $order_id . $status_code . $gross_amount . MIDTRANS_SERVER_KEY);

if ($signature_key !== $expected_signature) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit();
}

// ── TENTUKAN STATUS PEMBAYARAN ────────────────
$payment_status = 'pending';

if ($transaction_status === 'capture') {
    $payment_status = ($fraud_status === 'accept') ? 'paid' : 'fraud';
} elseif ($transaction_status === 'settlement') {
    $payment_status = 'paid';
} elseif (in_array($transaction_status, ['cancel', 'deny', 'expire'])) {
    $payment_status = 'failed';
} elseif ($transaction_status === 'pending') {
    $payment_status = 'pending';
}

// ── SIMPAN LOG NOTIFIKASI ─────────────────────
$log = [
    'order_id'           => $order_id,
    'transaction_status' => $transaction_status,
    'payment_status'     => $payment_status,
    'fraud_status'       => $fraud_status,
    'gross_amount'       => $gross_amount,
    'received_at'        => date('Y-m-d H:i:s'),
    'raw'                => $input,
];
file_put_contents(__DIR__ . '/notify_log.json', json_encode($log) . PHP_EOL, FILE_APPEND);

// ── JIKA PEMBAYARAN BERHASIL ──────────────────
if ($payment_status === 'paid') {

    // Load dependencies
    require_once __DIR__ . '/send_email.php';
    require_once __DIR__ . '/config.php';

    // Ambil data order dari log
    $order_data = [];
    $log_file   = __DIR__ . '/orders_log.json';
    if (file_exists($log_file)) {
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach (array_reverse($lines) as $line) {
            $entry = json_decode($line, true);
            if (isset($entry['order_id']) && $entry['order_id'] === $order_id) {
                $order_data = $entry;
                break;
            }
        }
    }

    $user_name  = $order_data['name']    ?? 'Pelanggan';
    $user_email = $order_data['email']   ?? '';
    $company    = $order_data['company'] ?? '';
    $plan       = $order_data['plan']    ?? 'pro';
    $amount     = $order_data['amount']  ?? 500000;

    // ── UPDATE STATUS PRO DI DATABASE ─────────────
    if ($user_email) {
        try {
            $pdo = getDB();

            // Cari user berdasarkan email, kalau tidak ada → insert baru
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $user_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Insert user baru
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, company_name, role, created_at)
                    VALUES (:username, :email, :password, :company, 'user', NOW())
                ");
                $stmt->execute([
                    ':username' => $user_name,
                    ':email'    => $user_email,
                    ':password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                    ':company'  => $company,
                ]);
                $user_id = $pdo->lastInsertId();
                error_log('[AMII] User baru dibuat: ' . $user_email);
            } else {
                $user_id = $user['id'];
            }

            // Cek apakah sudah ada subscription
            $stmt = $pdo->prepare("SELECT id FROM user_subscriptions WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update subscription yang ada
                $stmt = $pdo->prepare("
                    UPDATE user_subscriptions 
                    SET tier_id = 2, start_date = NOW(), end_date = DATE_ADD(NOW(), INTERVAL 1 MONTH), status = 'active'
                    WHERE user_id = :user_id
                ");
            } else {
                // Insert subscription baru
                $stmt = $pdo->prepare("
                    INSERT INTO user_subscriptions (user_id, tier_id, start_date, end_date, status)
                    VALUES (:user_id, 2, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 'active')
                ");
            }
            $stmt->execute([':user_id' => $user_id]);
            error_log('[AMII] Subscription updated untuk: ' . $user_email);

        } catch (Exception $e) {
            error_log('[AMII] Gagal update subscription: ' . $e->getMessage());
        }
    }

    // ── KIRIM EMAIL KONFIRMASI KE USER ────────────
    if ($user_email) {
        $subject_user = '✅ Pembayaran Berhasil — AMII Market Pro';
        $body_user    = emailTemplateSuccess($user_name, $order_id, 'Market Pro');
        sendEmail($user_email, $subject_user, $body_user);
    }

    // ── KIRIM NOTIFIKASI KE ADMIN ─────────────────
    $subject_admin = '💰 [AMII] Pembayaran Baru: ' . $order_id;
    $body_admin    = emailTemplateAdmin($user_name, $user_email, $company, $order_id, $plan, $amount);
    sendEmail(ADMIN_EMAIL, $subject_admin, $body_admin);

    echo json_encode(['status' => 'ok', 'message' => 'Payment processed & email sent: ' . $order_id]);

} else {
    echo json_encode(['status' => 'ok', 'message' => 'Notification received: ' . $payment_status]);
}