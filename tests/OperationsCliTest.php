<?php
declare(strict_types=1);
require_once __DIR__ . '/../operations.php';

$checks = 0;
function verify(bool $condition, string $name): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
}
function invalid(callable $callback, string $name): void
{
    global $checks;
    $checks++;
    try {
        $callback();
    } catch (InvalidArgumentException $expected) {
        return;
    }
    throw new RuntimeException('FAIL: ' . $name);
}
$data = ['itens' => [[
    'id' => 'item-1', 'objeto' => 'Guarda chuva', 'categoria' => 'objetos',
    'encontrado_em' => '2026-09-01', 'status' => 'pendente',
    'historico' => [['evento' => 'encontrado', 'data' => '2026-09-01']],
]]];
verify(operationsOptions(['--referencia=2026-11-08'])['referencia'] === '2026-11-08', 'date parsing');
verify(operationsOptions(['--dias=0'])['dias'] === '0', 'zero threshold parsing');
invalid(fn() => operationsOptions(['--dias=1', '--dias=2']), 'duplicate option');
invalid(fn() => operationsOptions(['--other=1']), 'unknown option');
invalid(fn() => operationsOptions(['--dias']), 'malformed option');
verify(operationsResult('diagnostico', $data, ['referencia' => '2026-11-08'])['pendentes'] === 1, 'diagnostics');
verify(count(operationsResult('pendencias', $data, ['referencia' => '2026-11-08', 'dias' => '30'])) === 1, 'stale listing');
verify(operationsResult('faixas', $data, ['referencia' => '2026-11-08'])['faixas_dias']['31-90'] === 1, 'aging buckets');
verify(operationsResult('fluxo', $data, ['referencia' => '2026-11-08'])['2026-09']['encontrados'] === 1, 'monthly flow');
verify(operationsResult('duplicidades', $data, ['janela' => '7']) === [], 'duplicates');
invalid(fn() => operationsResult('diagnostico', $data, ['dias' => '10']), 'wrong command option');
invalid(fn() => operationsResult('pendencias', $data, ['dias' => '-1']), 'negative age');
invalid(fn() => operationsResult('duplicidades', $data, ['janela' => '100000']), 'huge window');
invalid(fn() => operationsResult('unknown', $data, []), 'unknown command');
verify(str_contains(operationsUsage(), 'somente') || str_contains(operationsUsage(), 'Somente'), 'read only help');
echo "PASS: $checks CLI checks\n";
