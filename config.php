<?php
// Iniciar sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acesso - redirecionar se não estiver logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: emergency_login.php");
    exit;
}

// Definir variáveis básicas
$base_url = 'http://www.annemacedo.com.br/novo2';
$usuario_id = $_SESSION['user_id'];
$usuario_nome = $_SESSION['user_name'] ?? 'Vendedor';

// Conectar ao banco de dados
$db_host = 'mysql.annemacedo.com.br';
$db_name = 'annemacedo02';
$db_user = 'annemacedo02';
$db_pass = 'Vingador13Anne';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Funções úteis globais
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatPercentage($value) {
    return number_format((float)$value, 2, ',', '.') . '%';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Determinar qual página está ativa para o menu
$current_page = basename($_SERVER['PHP_SELF']);

// Array de configurações de menu
$menu = [
    'dashboard' => [
        'icon' => 'fas fa-tachometer-alt',
        'title' => 'Dashboard',
        'url' => 'dash_vendedor.php',
        'active' => ($current_page == 'dash_vendedor.php'),
        'submenu' => []
    ],
    'produtos' => [
        'icon' => 'fas fa-box',
        'title' => 'Produtos',
        'url' => '#',
        'active' => in_array($current_page, ['vendedor_produtos.php', 'vendedor_produtos_lista.php']),
        'submenu' => [
            [
                'title' => 'Cadastrar Produto',
                'url' => 'vendedor_produtos.php',
                'active' => ($current_page == 'vendedor_produtos.php')
            ],
            [
                'title' => 'Listar Produtos',
                'url' => 'vendedor_produtos_lista.php',
                'active' => ($current_page == 'vendedor_produtos_lista.php')
            ]
        ]
    ],
    'anuncios' => [
        'icon' => 'fas fa-tags',
        'title' => 'Anúncios',
        'url' => '#',
        'active' => ($current_page == 'vendedor_anuncios.php'),
        'submenu' => [
            [
                'title' => 'Mercado Livre',
                'url' => 'vendedor_anuncios.php',
                'active' => ($current_page == 'vendedor_anuncios.php')
            ]
        ]
    ],
    'calculadora' => [
        'icon' => 'fas fa-calculator',
        'title' => 'Calculadora',
        'url' => '#',
        'active' => ($current_page == 'calculadora.php'),
        'submenu' => [
            [
                'title' => 'Calcular Preço',
                'url' => 'calculadora.php',
                'active' => ($current_page == 'calculadora.php')
            ]
        ]
    ],
    'relatorios' => [
        'icon' => 'fas fa-chart-bar',
        'title' => 'Relatórios',
        'url' => '#',
        'active' => ($current_page == 'vendedor_relatorios.php'),
        'submenu' => []
    ],
    'curva_abc' => [
        'icon' => 'fas fa-chart-pie',
        'title' => 'Curva ABC',
        'url' => 'vendedor_analise_abc.php',
        'active' => ($current_page == 'vendedor_analise_abc.php'),
        'submenu' => []
    ],
    'configuracoes' => [
        'icon' => 'fas fa-cog',
        'title' => 'Configurações',
        'url' => '#',
        'active' => in_array($current_page, ['vendedor_config.php', 'vendedor_mercadolivre.php']),
        'submenu' => [
            [
                'title' => 'Configuração',
                'url' => 'vendedor_config.php',
                'active' => ($current_page == 'vendedor_config.php')
            ],
            [
                'title' => 'Integração',
                'url' => 'vendedor_mercadolivre.php',
                'active' => ($current_page == 'vendedor_mercadolivre.php')
            ]
        ]
    ]
];
?>