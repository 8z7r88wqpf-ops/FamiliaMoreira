<?php
$url = 'http://localhost/CompraDelMes/api/actualizar_precios.php';

$payload = json_encode(['action' => 'update_all']);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 120
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $status >= 400) {
    fwrite(STDERR, "Erro ao atualizar preços: " . ($error ?: "HTTP {$status}") . PHP_EOL);
    exit(1);
}

echo $response . PHP_EOL;
