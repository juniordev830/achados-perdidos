<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/ReturnReceipt.php';

$catalogue = [
  'itens' => [[
    'id' => 'obj-1',
    'status' => 'devolvido',
    'encontrado_em' => '2026-11-01',
    'devolvido_em' => '2026-11-04',
    'historico' => [
      ['evento' => 'encontrado', 'data' => '2026-11-01'],
      ['evento' => 'devolvido', 'data' => '2026-11-04'],
    ]
  ]]
];

$receipt = ReturnReceipt::build($catalogue, 'obj-1');
if ($receipt['status'] !== 'devolvido' ||
    $receipt['referencia'] !== ReturnReceipt::build($catalogue, 'obj-1')['referencia'] ||
    array_key_exists('name', $receipt)) {
    throw new RuntimeException('Recibo inconsistente');
}

function fails(callable $operation): void {
    try { $operation(); }
    catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('A operação inválida deveria falhar');
}

fails(fn() => ReturnReceipt::build($catalogue, 'id-inexistente'));
$pending = $catalogue;
$pending['itens'][0]['status'] = 'pendente';
fails(fn() => ReturnReceipt::build($pending, 'obj-1'));
$badDate = $catalogue;
$badDate['itens'][0]['devolvido_em'] = '2026-10-30';
fails(fn() => ReturnReceipt::build($badDate, 'obj-1'));
$badHistory = $catalogue;
$badHistory['itens'][0]['historico'] = [];
fails(fn() => ReturnReceipt::build($badHistory, 'obj-1'));

echo "Recibos de devolução: verificações OK\n";
