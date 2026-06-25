<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/middleware.php';
require_once __DIR__ . '/lib/precios_helpers.php';

handlePreflight();
requireMethod('POST');

$database = new Database();
$db = $database->getConnection();

// Leer el cuerpo de la petición para saber si incluir precios de ejemplo
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$incluirPrecios = $data['incluir_precios'] ?? true;

// ============================================================
// CATÁLOGO COMPLETO DE SUPERMERCADO
// ============================================================
$catalogo = [
    // ==================== ARROZ (Rice) ====================
    ['nome' => 'Arroz Agulha',                 'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Agulha',                 'marca' => 'Milaneza',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Agulha',                 'marca' => 'Cigala',    'categoria' => 'Arroz'],
    ['nome' => 'Arroz Carolino',               'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Carolino',               'marca' => 'Milaneza',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Carolino',               'marca' => 'Cigala',    'categoria' => 'Arroz'],
    ['nome' => 'Arroz Integral',               'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Integral',               'marca' => 'Milaneza',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Integral',               'marca' => 'Cigala',    'categoria' => 'Arroz'],
    ['nome' => 'Arroz Basmati',                'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Basmati',                'marca' => 'Tio João',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Basmati',                'marca' => 'Auchan',    'categoria' => 'Arroz'],
    ['nome' => 'Arroz Jasmine',                'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Jasmine',                'marca' => 'Tio João',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Sushi',                  'marca' => 'Yutaka',    'categoria' => 'Arroz'],
    ['nome' => 'Arroz Sushi',                  'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Selvagem',               'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz de Risoto',              'marca' => 'Arborio',   'categoria' => 'Arroz'],
    ['nome' => 'Arroz de Risoto',              'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Vaporizado',             'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Malandrinho',            'marca' => 'Caçarola',  'categoria' => 'Arroz'],
    ['nome' => 'Arroz Doce',                   'marca' => 'Caçarola',  'categoria' => 'Arroz'],

    // ==================== MASSAS (Pasta) ====================
    ['nome' => 'Esparguete',                   'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Esparguete',                   'marca' => 'Pirá',      'categoria' => 'Massas'],
    ['nome' => 'Esparguete',                   'marca' => 'Barilla',   'categoria' => 'Massas'],
    ['nome' => 'Esparguete Integral',           'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Macarrão',                     'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Macarrão',                     'marca' => 'Pirá',      'categoria' => 'Massas'],
    ['nome' => 'Macarrão',                     'marca' => 'Barilla',   'categoria' => 'Massas'],
    ['nome' => 'Penne',                        'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Penne',                        'marca' => 'Barilla',   'categoria' => 'Massas'],
    ['nome' => 'Penne',                        'marca' => 'Pirá',      'categoria' => 'Massas'],
    ['nome' => 'Fusilli',                      'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Fusilli',                      'marca' => 'Barilla',   'categoria' => 'Massas'],
    ['nome' => 'Lasanha',                      'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Lasanha',                      'marca' => 'Barilla',   'categoria' => 'Massas'],
    ['nome' => 'Tagliatelle',                  'marca' => 'Barilla',   'categoria' => 'Massas'],
    ['nome' => 'Tagliatelle',                  'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Parafuso',                     'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Cotovelos',                    'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Aletria',                      'marca' => 'Milaneza',  'categoria' => 'Massas'],
    ['nome' => 'Massa de Aveia',               'marca' => 'Milaneza',  'categoria' => 'Massas'],

    // ==================== LATICÍNIOS (Dairy) ====================
    ['nome' => 'Leite Meio Gordo',             'marca' => 'Mimosa',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Meio Gordo',             'marca' => 'Gresso',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Meio Gordo',             'marca' => 'Auchan',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Gordo',                  'marca' => 'Mimosa',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Gordo',                  'marca' => 'Gresso',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Magro',                  'marca' => 'Mimosa',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Magro',                  'marca' => 'Gresso',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Sem Lactose',            'marca' => 'Mimosa',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Sem Lactose',            'marca' => 'Auchan',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite de Soja',                'marca' => 'Alpro',     'categoria' => 'Laticínios'],
    ['nome' => 'Leite de Amêndoa',             'marca' => 'Alpro',     'categoria' => 'Laticínios'],
    ['nome' => 'Leite de Aveia',               'marca' => 'Alpro',     'categoria' => 'Laticínios'],
    ['nome' => 'Iogurte Natural',              'marca' => 'Danone',    'categoria' => 'Laticínios'],
    ['nome' => 'Iogurte Natural',              'marca' => 'Mimosa',    'categoria' => 'Laticínios'],
    ['nome' => 'Iogurte de Morango',           'marca' => 'Danone',    'categoria' => 'Laticínios'],
    ['nome' => 'Iogurte de Morango',           'marca' => 'Puleva',    'categoria' => 'Laticínios'],
    ['nome' => 'Iogurte Grego',                'marca' => 'Danone',    'categoria' => 'Laticínios'],
    ['nome' => 'Iogurte Grego',                'marca' => 'Mimosa',    'categoria' => 'Laticínios'],
    ['nome' => 'Queijo Flamengo',              'marca' => 'Lactim',    'categoria' => 'Laticínios'],
    ['nome' => 'Queijo Flamengo',              'marca' => 'Terra Nostra','categoria' => 'Laticínios'],
    ['nome' => 'Queijo da Ilha',               'marca' => 'Ilha',      'categoria' => 'Laticínios'],
    ['nome' => 'Queijo Fresco',                'marca' => 'Mimosa',    'categoria' => 'Laticínios'],
    ['nome' => 'Queijo Fresco',                'marca' => 'Terra Nostra','categoria' => 'Laticínios'],
    ['nome' => 'Queijo Curado',                'marca' => 'Terra Nostra','categoria' => 'Laticínios'],
    ['nome' => 'Queijo Curado',                'marca' => 'Lactim',    'categoria' => 'Laticínios'],
    ['nome' => 'Manteiga com Sal',             'marca' => 'Terra Nostra','categoria' => 'Laticínios'],
    ['nome' => 'Manteiga com Sal',             'marca' => 'Lactim',    'categoria' => 'Laticínios'],
    ['nome' => 'Manteiga sem Sal',             'marca' => 'Terra Nostra','categoria' => 'Laticínios'],
    ['nome' => 'Manteiga sem Sal',             'marca' => 'Lactim',    'categoria' => 'Laticínios'],
    ['nome' => 'Margarina',                    'marca' => 'Planta',    'categoria' => 'Laticínios'],
    ['nome' => 'Natas',                        'marca' => 'Parmalat',  'categoria' => 'Laticínios'],
    ['nome' => 'Natas',                        'marca' => 'Auchan',    'categoria' => 'Laticínios'],
    ['nome' => 'Leite Condensado',             'marca' => 'Moça',      'categoria' => 'Laticínios'],

    // ==================== CARNES (Meats) ====================
    ['nome' => 'Peito de Frango',              'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Coxa de Frango',               'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Frango Inteiro',               'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Frango Desfiado',              'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Bife de Vaca',                 'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Alcatra',                      'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Lombo de Porco',               'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Entrecosto',                   'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Costeleta de Porco',           'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Febras de Porco',              'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Carne Picada de Vaca',         'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Carne Picada de Porco',        'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Hambúrguer de Frango',         'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Hambúrguer de Vaca',           'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Salsichas',                    'marca' => '',           'categoria' => 'Carnes'],
    ['nome' => 'Bacon',                        'marca' => '',           'categoria' => 'Carnes'],

    // ==================== PEIXARIA (Fish) ====================
    ['nome' => 'Salmão Fresco',                'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Salmão Congelado',             'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Bacalhau Seco',                'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Bacalhau Fresco',              'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Pescada Fresca',               'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Pescada Congelada',            'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Dourada',                      'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Linguado',                     'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Atum Fresco',                  'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Robalo',                       'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Camarão Fresco',               'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Camarão Congelado',            'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Polvo Fresco',                 'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Polvo Congelado',              'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Amêijoas',                     'marca' => '',           'categoria' => 'Peixaria'],
    ['nome' => 'Mexilhões',                    'marca' => '',           'categoria' => 'Peixaria'],

    // ==================== FRUTAS (Fruits) ====================
    ['nome' => 'Maçã Gala',                    'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Maçã Golden',                  'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Maçã Fuji',                    'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Maçã Verde',                   'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Banana',                       'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Laranja',                      'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Laranja da Madeira',           'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Uva Branca',                   'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Uva Preta',                    'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Morango',                      'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Pêra Rocha',                   'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Pêra Conference',              'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Kiwi',                         'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Ananás',                       'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Melancia',                     'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Melão',                        'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Limão',                        'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Manga',                        'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Abacate',                      'marca' => '',           'categoria' => 'Frutas'],
    ['nome' => 'Cereja',                       'marca' => '',           'categoria' => 'Frutas'],

    // ==================== LEGUMES (Vegetables) ====================
    ['nome' => 'Batata',                       'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Batata Doce',                  'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Cenoura',                      'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Cebola',                       'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Cebola Roxa',                  'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Alho',                         'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Tomate',                       'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Tomate Cherry',                'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Alface',                       'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Espinafre',                    'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Brócolos',                     'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Couve-Flor',                   'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Couve Portuguesa',             'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Pimento Verde',                'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Pimento Vermelho',             'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Pepino',                       'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Courgette',                    'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Beringela',                    'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Feijão Verde',                 'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Ervilhas',                     'marca' => '',           'categoria' => 'Legumes'],
    ['nome' => 'Milho Doce',                   'marca' => '',           'categoria' => 'Legumes'],

    // ==================== BEBIDAS (Beverages) ====================
    ['nome' => 'Coca-Cola 2L',                 'marca' => 'Coca-Cola',  'categoria' => 'Refrigerantes'],
    ['nome' => 'Coca-Cola Lata',               'marca' => 'Coca-Cola',  'categoria' => 'Refrigerantes'],
    ['nome' => 'Coca-Cola Zero 2L',            'marca' => 'Coca-Cola',  'categoria' => 'Refrigerantes'],
    ['nome' => 'Coca-Cola Zero Lata',          'marca' => 'Coca-Cola',  'categoria' => 'Refrigerantes'],
    ['nome' => 'Fanta Laranja',                'marca' => 'Fanta',      'categoria' => 'Refrigerantes'],
    ['nome' => 'Fanta Laranja Zero',           'marca' => 'Fanta',      'categoria' => 'Refrigerantes'],
    ['nome' => 'Sprite',                       'marca' => 'Sprite',     'categoria' => 'Refrigerantes'],
    ['nome' => 'Sumol Laranja',                'marca' => 'Sumol',      'categoria' => 'Refrigerantes'],
    ['nome' => 'Sumol Ananás',                 'marca' => 'Sumol',      'categoria' => 'Refrigerantes'],
    ['nome' => 'Compal de Laranja',            'marca' => 'Compal',     'categoria' => 'Refrigerantes'],
    ['nome' => 'Compal de Manga',              'marca' => 'Compal',     'categoria' => 'Refrigerantes'],
    ['nome' => 'Compal de Maçã',               'marca' => 'Compal',     'categoria' => 'Refrigerantes'],
    ['nome' => 'Água Mineral Natural',         'marca' => 'Luso',       'categoria' => 'Águas'],
    ['nome' => 'Água Mineral Natural',         'marca' => 'Fastio',     'categoria' => 'Águas'],
    ['nome' => 'Água Mineral Natural',         'marca' => 'Vitalis',    'categoria' => 'Águas'],
    ['nome' => 'Água com Gás',                 'marca' => 'Luso',       'categoria' => 'Águas'],
    ['nome' => 'Água com Gás',                 'marca' => 'Fastio',     'categoria' => 'Águas'],
    ['nome' => 'Cerveja Super Bock',           'marca' => 'Super Bock', 'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Cerveja Sagres',               'marca' => 'Sagres',     'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Cerveja Heineken',             'marca' => 'Heineken',   'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Cerveja Corona',               'marca' => 'Corona',     'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho Tinto Reserva',          'marca' => 'Casal Garcia','categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho Tinto',                  'marca' => 'Mateus',     'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho Tinto',                  'marca' => 'Monte Velho','categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho Branco',                 'marca' => 'Casalinho',  'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho Branco',                 'marca' => 'Monte Velho','categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho Verde',                  'marca' => 'Gatão',      'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho Verde',                  'marca' => 'Casalinho',  'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Vinho do Porto',               'marca' => 'Sandeman',   'categoria' => 'Bebidas Alcoólicas'],
    ['nome' => 'Whisky',                       'marca' => 'Johnnie Walker','categoria' => 'Bebidas Alcoólicas'],

    // ==================== CAFÉS E CHÁS (Coffee & Tea) ====================
    ['nome' => 'Café Moído',                   'marca' => 'Delta',      'categoria' => 'Café'],
    ['nome' => 'Café Moído',                   'marca' => 'Sical',      'categoria' => 'Café'],
    ['nome' => 'Café Grão',                    'marca' => 'Delta',      'categoria' => 'Café'],
    ['nome' => 'Café Grão',                    'marca' => 'Buondi',     'categoria' => 'Café'],
    ['nome' => 'Cápsulas de Café',             'marca' => 'Delta',      'categoria' => 'Café'],
    ['nome' => 'Cápsulas de Café',             'marca' => 'Buondi',     'categoria' => 'Café'],
    ['nome' => 'Cápsulas de Café',             'marca' => 'Nescafé',    'categoria' => 'Café'],
    ['nome' => 'Café Solúvel',                 'marca' => 'Nescafé',    'categoria' => 'Café'],
    ['nome' => 'Chá Preto',                    'marca' => 'Lipton',     'categoria' => 'Chás'],
    ['nome' => 'Chá Verde',                    'marca' => 'Lipton',     'categoria' => 'Chás'],
    ['nome' => 'Chá de Camomila',              'marca' => 'Lipton',     'categoria' => 'Chás'],
    ['nome' => 'Chá de Hortelã',               'marca' => 'Lipton',     'categoria' => 'Chás'],
    ['nome' => 'Chá de Cidreira',              'marca' => 'Lipton',     'categoria' => 'Chás'],
    ['nome' => 'Chá de Frutos Vermelhos',      'marca' => 'Lipton',     'categoria' => 'Chás'],

    // ==================== CEREAIS E BARRAS (Cereals) ====================
    ['nome' => 'Corn Flakes',                   'marca' => 'Kellogg\'s','categoria' => 'Cereais'],
    ['nome' => 'Estrelitas',                    'marca' => 'Nestlé',    'categoria' => 'Cereais'],
    ['nome' => 'Chocapic',                      'marca' => 'Nestlé',    'categoria' => 'Cereais'],
    ['nome' => 'Fitness',                       'marca' => 'Nestlé',    'categoria' => 'Cereais'],
    ['nome' => 'Special K',                     'marca' => 'Kellogg\'s','categoria' => 'Cereais'],
    ['nome' => 'Coco Pops',                     'marca' => 'Kellogg\'s','categoria' => 'Cereais'],
    ['nome' => 'All-Bran',                      'marca' => 'Kellogg\'s','categoria' => 'Cereais'],
    ['nome' => 'Granola',                       'marca' => 'Auchan',    'categoria' => 'Cereais'],
    ['nome' => 'Granola',                       'marca' => 'Continente','categoria' => 'Cereais'],
    ['nome' => 'Flocos de Aveia',               'marca' => 'Auchan',    'categoria' => 'Cereais'],
    ['nome' => 'Flocos de Aveia',               'marca' => 'Quaker',    'categoria' => 'Cereais'],
    ['nome' => 'Barra de Cereais',             'marca' => 'Nutrigéna', 'categoria' => 'Cereais'],
    ['nome' => 'Barra de Cereais',             'marca' => 'Kellogg\'s','categoria' => 'Cereais'],
    ['nome' => 'Muesli',                        'marca' => 'Auchan',    'categoria' => 'Cereais'],

    // ==================== CONSERVAS (Canned) ====================
    ['nome' => 'Atum em Óleo',                 'marca' => 'Tombom',     'categoria' => 'Conservas'],
    ['nome' => 'Atum em Azeite',               'marca' => 'Tombom',     'categoria' => 'Conservas'],
    ['nome' => 'Atum Natural',                 'marca' => 'Tombom',     'categoria' => 'Conservas'],
    ['nome' => 'Atum em Óleo',                 'marca' => 'Continente', 'categoria' => 'Conservas'],
    ['nome' => 'Sardinhas em Óleo',            'marca' => 'Tombom',     'categoria' => 'Conservas'],
    ['nome' => 'Sardinhas em Azeite',          'marca' => 'Comur',      'categoria' => 'Conservas'],
    ['nome' => 'Sardinhas em Tomate',          'marca' => 'Tombom',     'categoria' => 'Conservas'],
    ['nome' => 'Cavala em Óleo',               'marca' => 'Tombom',     'categoria' => 'Conservas'],
    ['nome' => 'Ervilhas em Conserva',          'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Milho Doce em Conserva',        'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Grão-de-Bico',                 'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Feijão Frade',                 'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Feijão Manteiga',              'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Feijão Preto',                 'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Cogumelos em Conserva',         'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Tomate Pelado',                'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Tomate Pelado',                'marca' => 'Continente','categoria' => 'Conservas'],
    ['nome' => 'Tomate Polpa',                 'marca' => 'Auchan',    'categoria' => 'Conservas'],
    ['nome' => 'Tomate Polpa',                 'marca' => 'Continente','categoria' => 'Conservas'],

    // ==================== MOLHOS (Sauces) ====================
    ['nome' => 'Ketchup',                      'marca' => 'Heinz',      'categoria' => 'Molhos'],
    ['nome' => 'Ketchup',                      'marca' => 'Auchan',    'categoria' => 'Molhos'],
    ['nome' => 'Maionese',                     'marca' => 'Heinz',      'categoria' => 'Molhos'],
    ['nome' => 'Maionese',                     'marca' => 'Auchan',    'categoria' => 'Molhos'],
    ['nome' => 'Mostarda',                     'marca' => 'Heinz',      'categoria' => 'Molhos'],
    ['nome' => 'Molho de Tomate',              'marca' => 'Milaneza',  'categoria' => 'Molhos'],
    ['nome' => 'Molho de Tomate',              'marca' => 'Auchan',    'categoria' => 'Molhos'],
    ['nome' => 'Molho de Tomate com Manjericão','marca' => 'Barilla',   'categoria' => 'Molhos'],
    ['nome' => 'Molho Pesto',                  'marca' => 'Barilla',   'categoria' => 'Molhos'],
    ['nome' => 'Molho Pesto',                  'marca' => 'Sacla',     'categoria' => 'Molhos'],
    ['nome' => 'Molho Barbecue',               'marca' => 'Heinz',      'categoria' => 'Molhos'],
    ['nome' => 'Molho de Soja',                'marca' => 'Kikkoman',  'categoria' => 'Molhos'],
    ['nome' => 'Molho Inglês',                 'marca' => 'Lea & Perrins','categoria' => 'Molhos'],
    ['nome' => 'Vinagre de Vinho',             'marca' => 'Auchan',    'categoria' => 'Molhos'],
    ['nome' => 'Vinagre Balsâmico',            'marca' => 'Auchan',    'categoria' => 'Molhos'],
    ['nome' => 'Vinagre de Maçã',              'marca' => 'Auchan',    'categoria' => 'Molhos'],

    // ==================== ÓLEOS E AZEITES (Oils) ====================
    ['nome' => 'Azeite Virgem Extra',          'marca' => 'Gallo',      'categoria' => 'Azeite e Óleos'],
    ['nome' => 'Azeite Virgem Extra',          'marca' => 'Carbonell',  'categoria' => 'Azeite e Óleos'],
    ['nome' => 'Azeite Virgem Extra',          'marca' => 'Andorinha',  'categoria' => 'Azeite e Óleos'],
    ['nome' => 'Azeite Virgem Extra',          'marca' => 'Oliveira da Serra','categoria' => 'Azeite e Óleos'],
    ['nome' => 'Azeite Suave',                 'marca' => 'Gallo',      'categoria' => 'Azeite e Óleos'],
    ['nome' => 'Óleo Alimentar',               'marca' => 'Fula',       'categoria' => 'Azeite e Óleos'],
    ['nome' => 'Óleo Alimentar',               'marca' => 'Olá',        'categoria' => 'Azeite e Óleos'],
    ['nome' => 'Óleo de Girassol',             'marca' => 'Fula',       'categoria' => 'Azeite e Óleos'],
    ['nome' => 'Óleo de Coco',                 'marca' => 'Auchan',    'categoria' => 'Azeite e Óleos'],

    // ==================== TEMPEROS (Spices) ====================
    ['nome' => 'Sal de Mesa',                  'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Sal Marinho',                  'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Sal Grosso',                   'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Pimenta Preta Moída',          'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Pimenta Preta em Grão',        'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Orégãos',                      'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Louro',                        'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Alho em Pó',                   'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Cebola em Pó',                 'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Paprika',                      'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Cominhos',                     'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Canela em Pó',                 'marca' => 'Auchan',    'categoria' => 'Temperos'],
    ['nome' => 'Caldo de Galinha',             'marca' => 'Knorr',     'categoria' => 'Temperos'],
    ['nome' => 'Caldo de Galinha',             'marca' => 'Maggi',     'categoria' => 'Temperos'],
    ['nome' => 'Caldo de Legumes',             'marca' => 'Knorr',     'categoria' => 'Temperos'],
    ['nome' => 'Caldo de Carne',               'marca' => 'Knorr',     'categoria' => 'Temperos'],

    // ==================== AÇÚCAR E DOCES (Sugar & Sweets) ====================
    ['nome' => 'Açúcar Branco',                'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Açúcar Amarelo',               'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Açúcar Mascavado',             'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Açúcar em Pó',                 'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Açúcar Baunilhado',            'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Adoçante',                     'marca' => 'Canderel',  'categoria' => 'Açúcar'],
    ['nome' => 'Mel',                          'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Mel',                          'marca' => 'Continente','categoria' => 'Açúcar'],
    ['nome' => 'Geleia de Morango',            'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Geleia de Morango',            'marca' => 'Compal',    'categoria' => 'Açúcar'],
    ['nome' => 'Nutella',                      'marca' => 'Nutella',   'categoria' => 'Açúcar'],
    ['nome' => 'Nutella',                      'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Chocolate de Leite',           'marca' => 'Milka',     'categoria' => 'Açúcar'],
    ['nome' => 'Chocolate de Leite',           'marca' => 'Nestlé',    'categoria' => 'Açúcar'],
    ['nome' => 'Chocolate Branco',             'marca' => 'Milka',     'categoria' => 'Açúcar'],
    ['nome' => 'Chocolate Negro 70%',          'marca' => 'Lindt',     'categoria' => 'Açúcar'],
    ['nome' => 'Biscuit de Chocolate',         'marca' => 'Auchan',    'categoria' => 'Açúcar'],
    ['nome' => 'Bolacha Maria',                'marca' => 'Triunfo',   'categoria' => 'Açúcar'],
    ['nome' => 'Bolacha Água e Sal',           'marca' => 'Triunfo',   'categoria' => 'Açúcar'],
    ['nome' => 'Bolacha Torrada',              'marca' => 'Triunfo',   'categoria' => 'Açúcar'],
    ['nome' => 'Bolo de Arroz',                'marca' => 'Triunfo',   'categoria' => 'Açúcar'],

    // ==================== CONGELADOS (Frozen) ====================
    ['nome' => 'Pizza de Queijo',              'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Pizza de Queijo',              'marca' => 'Auchan',    'categoria' => 'Congelados'],
    ['nome' => 'Pizza Pepperoni',              'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Pizza de Frango',              'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Batata Frita Palitos',         'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Batata Frita Palitos',         'marca' => 'Auchan',    'categoria' => 'Congelados'],
    ['nome' => 'Batata Frita Rústica',         'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Legumes Salteados',            'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Legumes Salteados',            'marca' => 'Auchan',    'categoria' => 'Congelados'],
    ['nome' => 'Ervilhas Congeladas',          'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Espinafre Congelado',          'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Brócolos Congelados',          'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Gelado de Baunilha',           'marca' => 'Olá',        'categoria' => 'Congelados'],
    ['nome' => 'Gelado de Baunilha',           'marca' => 'Continente','categoria' => 'Congelados'],
    ['nome' => 'Gelado de Chocolate',          'marca' => 'Olá',        'categoria' => 'Congelados'],
    ['nome' => 'Gelado de Morango',            'marca' => 'Olá',        'categoria' => 'Congelados'],
    ['nome' => 'Rissóis de Camarão',           'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Rissóis de Carne',             'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Pastéis de Bacalhau',          'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Empadas de Frango',            'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Croquetes de Carne',           'marca' => 'Iglo',       'categoria' => 'Congelados'],
    ['nome' => 'Lasanha Congelada',            'marca' => 'Iglo',       'categoria' => 'Congelados'],

    // ==================== FARINHAS E PADARIA ====================
    ['nome' => 'Farinha de Trigo',             'marca' => 'Auchan',    'categoria' => 'Farinhas'],
    ['nome' => 'Farinha de Trigo Integral',    'marca' => 'Auchan',    'categoria' => 'Farinhas'],
    ['nome' => 'Farinha de Amêndoa',           'marca' => 'Auchan',    'categoria' => 'Farinhas'],
    ['nome' => 'Farinha de Milho',             'marca' => 'Auchan',    'categoria' => 'Farinhas'],
    ['nome' => 'Farinha de Centeio',           'marca' => 'Auchan',    'categoria' => 'Farinhas'],
    ['nome' => 'Pão de Forma',                 'marca' => 'Auchan',    'categoria' => 'Padaria'],
    ['nome' => 'Pão de Forma Integral',        'marca' => 'Auchan',    'categoria' => 'Padaria'],
    ['nome' => 'Pão de Forma de Centeio',      'marca' => 'Auchan',    'categoria' => 'Padaria'],
    ['nome' => 'Pão de Leite',                 'marca' => 'Auchan',    'categoria' => 'Padaria'],
    ['nome' => 'Torradas',                     'marca' => 'Torrié',    'categoria' => 'Padaria'],
    ['nome' => 'Torradas',                     'marca' => 'Auchan',    'categoria' => 'Padaria'],
    ['nome' => 'Pão de Hambúrguer',            'marca' => 'Auchan',    'categoria' => 'Padaria'],
    ['nome' => 'Pão de Cachorro Quente',       'marca' => 'Auchan',    'categoria' => 'Padaria'],

    // ==================== HIGIENE PESSOAL (Personal Hygiene) ====================
    ['nome' => 'Champô para Cabelo Normal',    'marca' => 'Dove',       'categoria' => 'Higiene'],
    ['nome' => 'Champô para Cabelos Secos',    'marca' => 'Dove',       'categoria' => 'Higiene'],
    ['nome' => 'Champô para Cabelos Oleosos',  'marca' => 'Head & Shoulders','categoria' => 'Higiene'],
    ['nome' => 'Condicionador',                'marca' => 'Dove',       'categoria' => 'Higiene'],
    ['nome' => 'Gel de Banho',                 'marca' => 'Dove',       'categoria' => 'Higiene'],
    ['nome' => 'Gel de Banho',                 'marca' => 'Nivea',      'categoria' => 'Higiene'],
    ['nome' => 'Sabonete Líquido',             'marca' => 'Dove',       'categoria' => 'Higiene'],
    ['nome' => 'Sabonete Líquido',             'marca' => 'Auchan',    'categoria' => 'Higiene'],
    ['nome' => 'Pasta de Dentes',              'marca' => 'Colgate',    'categoria' => 'Higiene'],
    ['nome' => 'Pasta de Dentes',              'marca' => 'Auchan',    'categoria' => 'Higiene'],
    ['nome' => 'Escova de Dentes',             'marca' => 'Oral-B',    'categoria' => 'Higiene'],
    ['nome' => 'Desodorizante Spray',          'marca' => 'Rexona',    'categoria' => 'Higiene'],
    ['nome' => 'Desodorizante Roll-on',        'marca' => 'Rexona',    'categoria' => 'Higiene'],
    ['nome' => 'Desodorizante em Barra',       'marca' => 'Rexona',    'categoria' => 'Higiene'],
    ['nome' => 'Papel Higiénico',              'marca' => 'Renova',    'categoria' => 'Higiene'],
    ['nome' => 'Papel Higiénico',              'marca' => 'Auchan',    'categoria' => 'Higiene'],
    ['nome' => 'Lenços de Papel',              'marca' => 'Renova',    'categoria' => 'Higiene'],
    ['nome' => 'Toalhitas Húmidas',            'marca' => 'Auchan',    'categoria' => 'Higiene'],
    ['nome' => 'Cotonetes',                    'marca' => 'Johnson & Johnson','categoria' => 'Higiene'],
    ['nome' => 'Fio Dentário',                 'marca' => 'Oral-B',    'categoria' => 'Higiene'],
    ['nome' => 'Lâminas de Barbear',           'marca' => 'Gillette',  'categoria' => 'Higiene'],
    ['nome' => 'Creme de Barbear',             'marca' => 'Gillette',  'categoria' => 'Higiene'],
    ['nome' => 'Protetor Solar FPS 50',        'marca' => 'Nivea',     'categoria' => 'Higiene'],
    ['nome' => 'Protetor Solar FPS 30',        'marca' => 'Nivea',     'categoria' => 'Higiene'],

    // ==================== LIMPEZA (Cleaning) ====================
    ['nome' => 'Detergente Líquido Roupa',     'marca' => 'Skip',       'categoria' => 'Limpeza'],
    ['nome' => 'Detergente Líquido Roupa',     'marca' => 'Auchan',    'categoria' => 'Limpeza'],
    ['nome' => 'Detergente Roupa Colorida',    'marca' => 'Skip',       'categoria' => 'Limpeza'],
    ['nome' => 'Amaciador Roupa',              'marca' => 'Comfort',    'categoria' => 'Limpeza'],
    ['nome' => 'Amaciador Roupa',              'marca' => 'Auchan',    'categoria' => 'Limpeza'],
    ['nome' => 'Lava-Loiça em Gel',            'marca' => 'Skip',       'categoria' => 'Limpeza'],
    ['nome' => 'Lava-Loiça em Pó',             'marca' => 'Skip',       'categoria' => 'Limpeza'],
    ['nome' => 'Pastilhas Máquina Loiça',      'marca' => 'Somat',     'categoria' => 'Limpeza'],
    ['nome' => 'Pastilhas Máquina Loiça',      'marca' => 'Auchan',    'categoria' => 'Limpeza'],
    ['nome' => 'Limpa-Vidros',                 'marca' => 'Ajax',       'categoria' => 'Limpeza'],
    ['nome' => 'Limpa-Tudo',                   'marca' => 'Cif',         'categoria' => 'Limpeza'],
    ['nome' => 'Desinfetante Superfícies',     'marca' => 'Sanytol',    'categoria' => 'Limpeza'],
    ['nome' => 'Lixívia',                      'marca' => 'Auchan',    'categoria' => 'Limpeza'],
    ['nome' => 'Detergente para Chão',         'marca' => 'Ajax',       'categoria' => 'Limpeza'],
    ['nome' => 'WC Polvo Limpeza',             'marca' => 'Harpic',     'categoria' => 'Limpeza'],
    ['nome' => 'Esponja Multiusos',            'marca' => 'Scotch-Brite','categoria' => 'Limpeza'],
    ['nome' => 'Saco de Lixo 50L',             'marca' => 'Auchan',    'categoria' => 'Limpeza'],
    ['nome' => 'Saco de Lixo 100L',            'marca' => 'Auchan',    'categoria' => 'Limpeza'],
    ['nome' => 'Película Aderente',            'marca' => 'Auchan',    'categoria' => 'Limpeza'],
    ['nome' => 'Papel Alumínio',               'marca' => 'Auchan',    'categoria' => 'Limpeza'],

    // ==================== OVOS E OVOPRODUTOS ====================
    ['nome' => 'Ovos de Galinha M',            'marca' => '',           'categoria' => 'Ovos'],
    ['nome' => 'Ovos de Galinha L',            'marca' => '',           'categoria' => 'Ovos'],
    ['nome' => 'Ovos de Galinha XL',           'marca' => '',           'categoria' => 'Ovos'],
    ['nome' => 'Ovos de Codorniz',             'marca' => '',           'categoria' => 'Ovos'],

    // ==================== CHARCUTARIA (Deli) ====================
    ['nome' => 'Fiambre de Frango',            'marca' => 'Primor',     'categoria' => 'Charcutaria'],
    ['nome' => 'Fiambre de Frango',            'marca' => 'Auchan',    'categoria' => 'Charcutaria'],
    ['nome' => 'Fiambre de Peru',              'marca' => 'Primor',     'categoria' => 'Charcutaria'],
    ['nome' => 'Presunto',                     'marca' => 'Primor',     'categoria' => 'Charcutaria'],
    ['nome' => 'Presunto Ibérico',             'marca' => 'Primor',     'categoria' => 'Charcutaria'],
    ['nome' => 'Chouriço de Carne',            'marca' => '',           'categoria' => 'Charcutaria'],
    ['nome' => 'Chouriço de Sangue',           'marca' => '',           'categoria' => 'Charcutaria'],
    ['nome' => 'Salpicão',                     'marca' => '',           'categoria' => 'Charcutaria'],
    ['nome' => 'Morcela',                      'marca' => '',           'categoria' => 'Charcutaria'],
    ['nome' => 'Paio',                         'marca' => '',           'categoria' => 'Charcutaria'],
    ['nome' => 'Linguiça',                     'marca' => '',           'categoria' => 'Charcutaria'],
    ['nome' => 'Mortadela',                    'marca' => '',           'categoria' => 'Charcutaria'],
    ['nome' => 'Salame',                       'marca' => '',           'categoria' => 'Charcutaria'],
];

$db->beginTransaction();
try {
    $countProductos = 0;
    $countPrecios = 0;

    // Primero limpiamos los productos existentes? No, mejor añadir a los existentes
    // pero primero verificamos si ya existe para no duplicar

    $stmtCheck = $db->prepare("
        SELECT COUNT(*) as cnt FROM productos
        WHERE LOWER(nome) = LOWER(:nome)
          AND COALESCE(LOWER(marca), '') = COALESCE(LOWER(:marca), '')
    ");

    $stmtInsert = $db->prepare("
        INSERT INTO productos (nome, marca, categoria)
        VALUES (:nome, :marca, :categoria)
    ");

    foreach ($catalogo as $producto) {
        $nome = normalizeText($producto['nome']);
        $marca = normalizeText($producto['marca']);
        $categoria = normalizeText($producto['categoria']);

        $stmtCheck->execute([
            ':nome' => $nome,
            ':marca' => $marca,
        ]);
        $existing = $stmtCheck->fetch();

        if ($existing['cnt'] == 0) {
            $stmtInsert->execute([
                ':nome' => $nome,
                ':marca' => $marca !== '' ? $marca : null,
                ':categoria' => $categoria !== '' ? $categoria : null,
            ]);
            $countProductos++;
        }
    }

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => "Catálogo populado com sucesso! $countProductos novos produtos adicionados.",
        'productos_criados' => $countProductos,
    ]);
} catch (Exception $e) {
    $db->rollBack();
    jsonError('Erro ao popular catálogo: ' . $e->getMessage(), 500);
}