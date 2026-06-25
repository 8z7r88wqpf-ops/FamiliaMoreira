<?php
$html = file_get_contents(__DIR__ . '/debug_continente.html');
echo "Tamaño: " . strlen($html) . " bytes\n\n";

// 1. Buscar TODOS los precios con clase pwc-tile--price-primary
$p1 = '/<span[^>]*class="[^"]*pwc-tile--price-primary[^"]*"[^>]*>([^<]+)<\/span>/i';
preg_match_all($p1, $html, $m1);
echo "Precios (pwc-tile--price-primary): " . count($m1[1]) . "\n";
foreach (array_slice($m1[1], 0, 5) as $p) echo "  -> " . trim(strip_tags($p)) . "\n";

// 2. Buscar nombres de producto (h3 dentro de product-tile)
$p2 = '/<div[^>]*class="[^"]*product-tile[^"]*"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>/is';
preg_match_all($p2, $html, $m2, PREG_SET_ORDER);
echo "\nNombres (h3 en product-tile): " . count($m2) . "\n";
foreach (array_slice($m2, 0, 5) as $r) echo "  -> " . trim(strip_tags($r[1])) . "\n";

// 3. Buscar enlaces a produtos
$p3 = '/<a[^>]*href="(\/produto\/[^"]+)"[^>]*>/i';
preg_match_all($p3, $html, $m3);
echo "\nEnlaces a produto: " . count($m3[1]) . "\n";
foreach (array_slice($m3[1], 0, 5) as $u) echo "  -> https://www.continente.pt$u\n";

// 4. Buscar estructura completa: tile con nombre y precio
$p4 = '/<div[^>]*class="[^"]*product-tile[^"]*"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*pwc-tile--price-primary[^"]*"[^>]*>([^<]+)<\/span>.*?<a[^>]*href="([^"]+)"[^>]*>/is';
preg_match_all($p4, $html, $m4, PREG_SET_ORDER);
echo "\nEstructura completa (nombre + precio + enlace): " . count($m4) . "\n";
foreach (array_slice($m4, 0, 5) as $r) {
    $name = trim(strip_tags($r[1]));
    $price = trim(strip_tags($r[2]));
    $url = $r[3];
    if (strpos($url, 'http') !== 0) $url = 'https://www.continente.pt' . $url;
    echo "  -> $name = $price ($url)\n";
}

// 5. Buscar en debug_pingodoce.html
echo "\n=== PINGO DOCE ===\n";
$html2 = file_get_contents(__DIR__ . '/debug_pingodoce.html');
echo "Tamaño: " . strlen($html2) . " bytes\n";

// Buscar precios
$pp1 = '/<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/i';
preg_match_all($pp1, $html2, $mp1);
echo "Precios (class=price): " . count($mp1[1]) . "\n";
foreach (array_slice($mp1[1], 0, 10) as $p) echo "  -> " . trim(strip_tags($p)) . "\n";

// Buscar nombres
$pp2 = '/<h3[^>]*>([^<]+)<\/h3>/i';
preg_match_all($pp2, $html2, $mp2);
echo "\nNombres (h3): " . count($mp2[1]) . "\n";
foreach (array_slice($mp2[1], 0, 10) as $n) echo "  -> " . trim(strip_tags($n)) . "\n";

// Buscar enlaces
$pp3 = '/<a[^>]*href="([^"]+)"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/is';
preg_match_all($pp3, $html2, $mp3, PREG_SET_ORDER);
echo "\nEstructura completa Pingo Doce: " . count($mp3) . "\n";
foreach (array_slice($mp3, 0, 5) as $r) {
    $url = $r[1];
    if (strpos($url, 'http') !== 0) $url = 'https://www.pingodoce.pt' . $url;
    echo "  -> " . trim(strip_tags($r[2])) . " = " . trim(strip_tags($r[3])) . " ($url)\n";
}

// 6. Buscar en debug_auchan.html
echo "\n=== AUCHAN ===\n";
$html3 = file_get_contents(__DIR__ . '/debug_auchan.html');
echo "Tamaño: " . strlen($html3) . " bytes\n";

$pa1 = '/<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/i';
preg_match_all($pa1, $html3, $ma1);
echo "Precios (class=price): " . count($ma1[1]) . "\n";
foreach (array_slice($ma1[1], 0, 10) as $p) echo "  -> " . trim(strip_tags($p)) . "\n";

$pa2 = '/<h3[^>]*>([^<]+)<\/h3>/i';
preg_match_all($pa2, $html3, $ma2);
echo "\nNombres (h3): " . count($ma2[1]) . "\n";
foreach (array_slice($ma2[1], 0, 10) as $n) echo "  -> " . trim(strip_tags($n)) . "\n";

// 7. debug_mercadona.json
echo "\n=== MERCADONA ===\n";
$json = file_get_contents(__DIR__ . '/debug_mercadona.json');
$data = json_decode($json, true);
if ($data && isset($data['results'])) {
    echo "Resultados: " . count($data['results']) . "\n";
    foreach (array_slice($data['results'], 0, 5) as $p) {
        $name = $p['display_name'] ?? $p['name'] ?? '?';
        $price = $p['price_instructions']['unit']['price'] ?? $p['price_instructions']['unit_price'] ?? '?';
        echo "  -> $name = $price\n";
    }
} else {
    echo "No se pudo decodificar JSON o no hay results\n";
    echo "Contenido: " . substr($json, 0, 500) . "\n";
}

// 8. debug_lidl.html
echo "\n=== LIDL ===\n";
$html4 = file_get_contents(__DIR__ . '/debug_lidl.html');
echo "Tamaño: " . strlen($html4) . " bytes\n";
$pl1 = '/<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/i';
preg_match_all($pl1, $html4, $ml1);
echo "Precios (class=price): " . count($ml1[1]) . "\n";
foreach (array_slice($ml1[1], 0, 10) as $p) echo "  -> " . trim(strip_tags($p)) . "\n";