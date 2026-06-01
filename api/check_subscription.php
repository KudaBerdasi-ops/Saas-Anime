<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$email = trim($_GET['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'is_pro' => false, 'message' => 'Email tidak valid']);
    exit();
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT us.status, us.end_date, us.tier_id
        FROM users u
        JOIN user_subscriptions us ON u.id = us.user_id
        WHERE u.email = :email
        AND us.status = 'active'
        AND us.end_date >= NOW()
        AND us.tier_id = 2
        ORDER BY us.end_date DESC
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sub) {
        echo json_encode([
            'status'   => 'ok',
            'is_pro'   => true,
            'end_date' => $sub['end_date']
        ]);
    } else {
        echo json_encode([
            'status' => 'ok',
            'is_pro' => false
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'is_pro' => false, 'message' => $e->getMessage()]);
}