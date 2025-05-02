<?php
// Este arquivo define as categorias do Mercado Livre e suas respectivas taxas

$categorias_ml = [
    'eletronicos' => [
        'nome' => 'Eletrônicos',
        'taxa' => 13
    ],
    'celulares' => [
        'nome' => 'Celulares e Telefones',
        'taxa' => 11
    ],
    'informatica' => [
        'nome' => 'Informática',
        'taxa' => 12
    ],
    'moda' => [
        'nome' => 'Moda e Acessórios',
        'taxa' => 18
    ],
    'casa_decoracao' => [
        'nome' => 'Casa e Decoração',
        'taxa' => 17
    ],
    'eletrodomesticos' => [
        'nome' => 'Eletrodomésticos',
        'taxa' => 14
    ],
    'beleza' => [
        'nome' => 'Beleza e Cuidado Pessoal',
        'taxa' => 16
    ],
    'esportes' => [
        'nome' => 'Esportes e Fitness',
        'taxa' => 15
    ],
    'brinquedos' => [
        'nome' => 'Brinquedos e Hobbies',
        'taxa' => 14
    ],
    'saude' => [
        'nome' => 'Saúde',
        'taxa' => 16
    ],
    'ferramentas' => [
        'nome' => 'Ferramentas e Construção',
        'taxa' => 13
    ],
    'industria' => [
        'nome' => 'Indústria e Comércio',
        'taxa' => 14
    ],
    'agro' => [
        'nome' => 'Agro, Indústria e Comércio',
        'taxa' => 12
    ],
    'imoveis' => [
        'nome' => 'Imóveis',
        'taxa' => 10
    ],
    'servicos' => [
        'nome' => 'Serviços',
        'taxa' => 20
    ],
    'outros' => [
        'nome' => 'Outras categorias',
        'taxa' => 15
    ]
];

// Retorno para uso em funções JavaScript no front-end
if (isset($_GET['json']) && $_GET['json'] == 1) {
    header('Content-Type: application/json');
    echo json_encode($categorias_ml);
    exit;
}
?>
