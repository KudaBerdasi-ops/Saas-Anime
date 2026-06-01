<?php
$api_key = 're_eCPVJiu9_Epf4S3pkPaYmKYu8SaQpHxEG';

$data = [
    'from'    => 'AMII.AI <onboarding@resend.dev>',
    'to' => ['kyysepuh155@gmail.com'],
    'subject' => 'Test Email AMII',
    'html'    => '<h1>Test berhasil!</h1>',
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
$curl_error = curl_error($ch);
curl_close($ch);

echo 'HTTP Code: ' . $http_code . '<br>';
echo 'Response: ' . $response . '<br>';
echo 'Curl Error: ' . $curl_error;