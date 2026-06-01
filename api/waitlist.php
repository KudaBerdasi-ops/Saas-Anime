<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status'=>'error','message'=>'Method not allowed']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$name    = trim($body['name']    ?? '');
$email   = trim($body['email']   ?? '');
$company = trim($body['company'] ?? '');
$plan    = trim($body['plan']    ?? '');
$message = trim($body['message'] ?? '');

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Nama dan email wajib diisi']);
    exit();
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO waitlist (name, email, company, plan, message)
        VALUES (:name, :email, :company, :plan, :message)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name), company=VALUES(company),
            plan=VALUES(plan), message=VALUES(message)
    ");
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':company' => $company,
        ':plan'    => $plan,
        ':message' => $message,
    ]);
    echo json_encode(['status'=>'ok','message'=>'Berhasil didaftarkan!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}