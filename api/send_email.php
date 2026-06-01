<?php
define('ADMIN_EMAIL', 'hengkysetiabudi155@gmail.com');

function sendEmail($to, $subject, $html_body) {
    $api_key = 're_eCPVJiu9_Epf4S3pkPaYmKYu8SaQpHxEG';
    
    $data = [
        'from'    => 'AMII.AI <onboarding@resend.dev>',
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html_body,
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ]);
    
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log('[Resend] Code: ' . $http_code . ' Response: ' . $response);
    return $http_code === 200;
}

function emailTemplateSuccess($name, $order_id, $plan = 'Market Pro') {
    return '<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#050508;font-family:\'Segoe UI\',sans-serif;">
  <table width="100%" style="background:#050508;padding:40px 0;">
    <tr><td align="center">
      <table width="560" style="background:#12141c;border-radius:24px;border:1px solid rgba(255,255,255,0.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);padding:40px;text-align:center;border-radius:24px 24px 0 0;">
            <div style="font-size:48px;">🎉</div>
            <h1 style="color:#fff;font-size:26px;margin:0;">Pembayaran Berhasil!</h1>
            <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px;">AMII.AI — Anime Market Intelligence Indonesia</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px;">
            <p style="color:#94a3b8;font-size:15px;line-height:1.7;margin:0 0 24px;">
              Hei <strong style="color:#fff;">' . htmlspecialchars($name) . '</strong>,<br><br>
              Pembayaran langganan <strong style="color:#a78bfa;">' . $plan . '</strong> kamu telah berhasil!
            </p>
            <table width="100%" style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.25);border-radius:16px;margin-bottom:28px;">
              <tr><td style="padding:24px;">
                <p style="color:#94a3b8;font-size:11px;font-weight:700;text-transform:uppercase;margin:0 0 16px;">Detail Transaksi</p>
                <table width="100%">
                  <tr>
                    <td style="color:#64748b;font-size:13px;padding:6px 0;">Order ID</td>
                    <td style="color:#a78bfa;font-size:13px;font-weight:700;text-align:right;font-family:monospace;">' . htmlspecialchars($order_id) . '</td>
                  </tr>
                  <tr>
                    <td style="color:#64748b;font-size:13px;padding:6px 0;">Plan</td>
                    <td style="color:#fff;font-size:13px;font-weight:700;text-align:right;">' . $plan . '</td>
                  </tr>
                  <tr>
                    <td style="color:#64748b;font-size:13px;padding:6px 0;">Status</td>
                    <td style="text-align:right;"><span style="background:rgba(16,185,129,0.15);color:#10b981;font-size:11px;font-weight:800;padding:3px 10px;border-radius:100px;">✓ LUNAS</span></td>
                  </tr>
                </table>
              </td></tr>
            </table>
            <table width="100%">
              <tr><td align="center">
                <a href="https://amii.great-site.net" style="display:inline-block;background:#8b5cf6;color:#fff;font-size:15px;font-weight:700;padding:16px 40px;border-radius:14px;text-decoration:none;">
                  Masuk ke Dashboard →
                </a>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 40px;border-top:1px solid rgba(255,255,255,0.06);text-align:center;">
            <p style="color:#475569;font-size:12px;margin:0;">© ' . date('Y') . ' AMII.AI</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
}

function emailTemplateAdmin($name, $email, $company, $order_id, $plan, $amount) {
    return '<!DOCTYPE html>
<html>
<body style="font-family:\'Segoe UI\',sans-serif;background:#050508;padding:40px;">
  <table width="560" style="background:#12141c;border-radius:16px;border:1px solid rgba(255,255,255,0.08);padding:32px;margin:auto;">
    <tr><td>
      <h2 style="color:#a78bfa;margin:0 0 8px;">💰 Pembayaran Baru!</h2>
      <table width="100%">
        <tr><td style="color:#64748b;font-size:13px;padding:8px 0;">Nama</td>
            <td style="color:#fff;font-size:13px;font-weight:700;text-align:right;">' . htmlspecialchars($name) . '</td></tr>
        <tr><td style="color:#64748b;font-size:13px;padding:8px 0;">Email</td>
            <td style="color:#a78bfa;font-size:13px;text-align:right;">' . htmlspecialchars($email) . '</td></tr>
        <tr><td style="color:#64748b;font-size:13px;padding:8px 0;">Order ID</td>
            <td style="color:#a78bfa;font-size:12px;font-family:monospace;text-align:right;">' . htmlspecialchars($order_id) . '</td></tr>
        <tr><td style="color:#64748b;font-size:13px;padding:8px 0;">Total</td>
            <td style="color:#10b981;font-size:15px;font-weight:800;text-align:right;">Rp ' . number_format($amount, 0, ',', '.') . '</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
}