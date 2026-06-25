<?php
/**
 * Script de prueba para ver qué HTML devuelven los supermercados
 */
require_once __DIR__ . '/api/config/database.php';

function fetchUrl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: pt-PT,pt;q=0.9,en;q=0.7',
        ],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Status: $status\n";
    return $body;
}

echo "=== CONTINENTE (arroz) ===\n";
$html = fetchUrl('https://www.continente.pt/pesquisa/?q=arroz');
if ($html) {
    // Guardar HTML para análisis
    file_put_contents(__DIR__ . '/debug_continente.html', $html);
    echo "HTML guardado en debug_continente.html (" . strlen($html) . " bytes)\n";
    
    // Buscar productos con varios patrones
    $patterns = [
        'product-link' => '/<a[^>]*class="[^"]*product-link[^"]*"[^>]*href="([^"]+)"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*(?:price|ct-price-value)[^"]*"[^>]*>([^<]+)<\/span>/is',
        'product simple' => '/<a[^>]*href="(\/produto\/[^"]+)"[^>]*>.*?<span[^>]*class="[^"]*title[^"]*"[^>]*>([^<]+)<\/span>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/is',
        'generic product' => '/<div[^>]*class="[^"]*product[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<[^>]+>([^<]+)<\/[^>]+>.*?(?:€|&euro;)\s*([0-9]+(?:[,.][0-9]{1,2})?)/is',
        'ct-grid' => '/<div[^>]*class="[^"]*ct-grid-item[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<span[^>]*class="[^"]*ct-product-name[^"]*"[^>]*>([^<]+)<\/span>.*?<span[^>]*class="[^"]*ct-price-value[^"]*"[^>]*>([^<]+)<\/span>/is',
        'item product' => '/<div[^>]*class="[^"]*item-product[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<[^>]+class="[^"]*title[^"]*"[^>]*>([^<]+)<.*?<span[^>]*class="[^"]*ct-price-value[^"]*"[^>]*>([^<]+)<\/span>/is',
        'price any' => '/(?:€|&euro;)\s*([0-9]+[,.][0-9]{1,2})/',
        'data-price' => '/data-price=["\']([^"\']+)["\']/',
        'json-ld' => '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
    ];
    
    foreach ($patterns as $name => $pattern) {
        $count = preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
        echo "  [$name]: $count coincidencias\n";
        if ($count > 0) {
            foreach (array_slice($matches, 0, 3) as $m) {
                echo "    -> " . substr(strip_tags(implode(' | ', $m)), 0, 200) . "\n";
            }
        }
    }
    
    // Buscar fragmentos con "€" y palabra alrededor
    preg_match_all('/<[^>]*>[^<]*(?:€|&euro;)[^<]*<\/[^>]*>/i', $html, $euroMatches);
    echo "  Fragmentos con €: " . count($euroMatches[0]) . "\n";
    foreach (array_slice($euroMatches[0], 0, 10) as $frag) {
        echo "    -> " . trim(strip_tags($frag)) . "\n";
    }
    
    // Buscar nombres de clase con "price" o "preco"
    preg_match_all('/class="([^"]*price[^"]*)"/i', $html, $classMatches);
    $classes = array_unique($classMatches[1]);
    echo "  Clases CSS con 'price': " . count($classes) . "\n";
    foreach (array_slice($classes, 0, 15) as $c) {
        echo "    -> $c\n";
    }
    
    preg_match_all('/class="([^"]*product[^"]*)"/i', $html, $classMatches2);
    $classes2 = array_unique($classMatches2[1]);
    echo "  Clases CSS con 'product': " . count($classes2) . "\n";
    foreach (array_slice($classes2, 0, 15) as $c) {
        echo "    -> $c\n";
    }
}

echo "\n=== PINGO DOCE (arroz) ===\n";
$html2 = fetchUrl('https://www.pingodoce.pt/produtos/?s=arroz');
if ($html2) {
    file_put_contents(__DIR__ . '/debug_pingodoce.html', $html2);
    echo "HTML guardado en debug_pingodoce.html (" . strlen($html2) . " bytes)\n";
    
    $patterns2 = [
        'product-item' => '/<div[^>]*class="[^"]*product-item[^"]*"[^>]*>.*?<h3[^>]*class="[^"]*product-title[^"]*"[^>]*>([^<]+)<\/h3>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>.*?<a[^>]*href="([^"]+)"[^>]*>/is',
        'generic product' => '/<div[^>]*class="[^"]*product[^"]*"[^>]*>.*?<a[^>]*href="([^"]+)"[^>]*>.*?<img[^>]*alt="([^"]+)"[^>]*>.*?<span[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/span>/is',
    ];
    
    foreach ($patterns2 as $name => $pattern) {
        $count = preg_match_all($pattern, $html2, $matches, PREG_SET_ORDER);
        echo "  [$name]: $count coincidencias\n";
    }
    
    preg_match_all('/class="([^"]*price[^"]*)"/i', $html2, $classMatches);
    echo "  Clases CSS con 'price':\n";
    foreach (array_unique($classMatches[1]) as $c) {
        echo "    -> $c\n";
    }
}

echo "\n=== AUCHAN (arroz) ===\n";
$html3 = fetchUrl('https://www.auchan.pt/pt/produtos/?q=arroz');
if ($html3) {
    file_put_contents(__DIR__ . '/debug_auchan.html', $html3);
    echo "HTML guardado en debug_auchan.html (" . strlen($html3) . " bytes)\n";
    
    preg_match_all('/class="([^"]*price[^"]*)"/i', $html3, $classMatches);
    echo "  Clases CSS con 'price':\n";
    foreach (array_unique($classMatches[1]) as $c) {
        echo "    -> $c\n";
    }
}

echo "\n=== MERCADONA (arroz) ===\n";
$json = fetchUrl('https://www.mercadona.pt/api/v1/products/search?query=arroz');
if ($json) {
    file_put_contents(__DIR__ . '/debug_mercadona.json', $json);
    $data = json_decode($json, true);
    echo "Resultados: " . ($data ? count($data['results'] ?? []) : 'no json') . "\n";
}

echo "\n=== LIDL (arroz) ===\n";
$html4 = fetchUrl('https://www.lidl.pt/search?q=arroz');
if ($html4) {
    file_put_contents(__DIR__ . '/debug_lidl.html', $html4);
    echo "HTML guardado en debug_lidl.html (" . strlen($html4) . " bytes)\n";
    
    preg_match_all('/class="([^"]*price[^"]*)"/i', $html4, $classMatches);
    echo "  Clases CSS con 'price':\n";
    foreach (array_unique($classMatches[1]) as $c) {
        echo "    -> $c\n";
    }
}

echo "\n¡Revisa los archivos debug_*.html y debug_*.json para ver la estructura real!\n";
