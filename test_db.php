<?php
$pdo = new PDO('mysql:host=localhost;dbname=poupamercado', 'root', '');
$stmt = $pdo->query('SELECT * FROM supermercados');
echo "=== SUPERMERCADOS ===\n";
foreach ($stmt as $row) {
    print_r($row);
}

$stmt = $pdo->query('SELECT * FROM productos LIMIT 10');
echo "\n=== PRODUCTOS (10) ===\n";
foreach ($stmt as $row) {
    print_r($row);
}

$stmt = $pdo->query('SELECT * FROM precios LIMIT 10');
echo "\n=== PRECIOS (10) ===\n";
foreach ($stmt as $row) {
    print_r($row);
}