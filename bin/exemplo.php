<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Catalogo.php';

use AchadosPerdidos\Catalogo;

$catalogo = new Catalogo();
$catalogo->cadastrar('demo-001', 'Acessórios', 'Guarda-chuva preto', '2026-11-01');
$catalogo->cadastrar('demo-002', 'Roupas', 'Casaco cinza', '2026-11-02');
$catalogo->marcarDevolucao('demo-001', '2026-11-03');

echo "Objetos pendentes:\n";
echo json_encode(
    $catalogo->pendentes(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
) . "\n";
