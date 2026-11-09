<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Operations.php';

$checks = 0;
$errors = [];
function expect(bool $value, string $label): void
{
    global $checks, $errors;
    $checks++;
    if (!$value) $errors[] = $label;
}
function expectInvalid(callable $callback, string $label): void
{
    global $checks, $errors;
    $checks++;
    try {
        $callback();
        $errors[] = $label . ' accepted invalid input';
    } catch (InvalidArgumentException $expected) {
        // Input correctly rejected.
    }
}
function entry(string $id, string $name, string $category, string $found, ?string $returned = null): array
{
    $item = [
        'id' => $id,
        'objeto' => $name,
        'categoria' => $category,
        'encontrado_em' => $found,
        'status' => $returned === null ? 'pendente' : 'devolvido',
        'historico' => [['evento' => 'encontrado', 'data' => $found]],
    ];
    if ($returned !== null) {
        $item['devolvido_em'] = $returned;
        $item['historico'][] = ['evento' => 'devolvido', 'data' => $returned];
    }
    return $item;
}
$data = ['itens' => [
    entry('a-1', 'Garrafa azul', 'objetos', '2026-09-01'),
    entry('a-2', ' garrafa   azul ', 'objetos', '2026-09-03'),
    entry('b-1', 'Casaco', 'roupas', '2026-10-10', '2026-11-02'),
    entry('c-1', 'Chaves', 'objetos', '2026-11-04'),
    entry('d-1', 'Casaco', 'roupas', '2026-10-11', '2026-10-20'),
    entry('e-1', 'Fone', 'eletronicos', '2026-12-01'),
]];

$asOf = CatalogOperations::backlogAsOf($data, '2026-11-08');
expect($asOf['pendentes_na_data'] === 3, 'as-of pending');
expect($asOf['devolvidos_ate_a_data'] === 2, 'as-of returned');
expect($asOf['encontrados_depois_da_data'] === 1, 'as-of future excluded');
expect($asOf['ids_pendentes'] === ['a-1', 'a-2', 'c-1'], 'as-of pending ids');
$beforeReturn = CatalogOperations::backlogAsOf($data, '2026-10-15');
expect($beforeReturn['pendentes_na_data'] === 4, 'later returns pending in earlier snapshot');
expect($beforeReturn['devolvidos_ate_a_data'] === 0, 'no early returns');
expect($beforeReturn['encontrados_depois_da_data'] === 2, 'not yet found');
$empty = CatalogOperations::backlogAsOf(['itens' => []], '2026-11-08');
expect($empty['pendentes_na_data'] === 0, 'empty backlog');

$stale = CatalogOperations::staleItems($data, '2026-11-08', 30);
expect(count($stale) === 2, 'stale threshold');
expect($stale[0]['id'] === 'a-1', 'oldest item first');
expect($stale[0]['dias_sem_devolucao'] === 68, 'age in days');
expect(count(CatalogOperations::staleItems($data, '2026-11-08', 0)) === 3, 'all open items');
expect(count(CatalogOperations::staleItems($data, '2026-11-08', 365)) === 0, 'no very old items');
expect(count(CatalogOperations::staleItems($data, '2026-09-02', 0)) === 1, 'early snapshot');

$buckets = CatalogOperations::agingBuckets($data, '2026-11-08');
expect($buckets['total_pendentes'] === 3, 'aging total');
expect($buckets['faixas_dias']['0-7'] === 1, 'fresh bucket');
expect($buckets['faixas_dias']['8-30'] === 0, 'middle bucket');
expect($buckets['faixas_dias']['31-90'] === 2, 'old bucket');
expect($buckets['faixas_dias']['91+'] === 0, 'very old bucket');

$flow = CatalogOperations::monthlyFlow($data, '2026-11-08');
expect($flow['2026-09']['encontrados'] === 2, 'September found');
expect($flow['2026-09']['saldo_pendente'] === 2, 'September backlog');
expect($flow['2026-10']['encontrados'] === 2, 'October found');
expect($flow['2026-10']['devolvidos'] === 1, 'October returns');
expect($flow['2026-10']['saldo_pendente'] === 3, 'October backlog');
expect($flow['2026-11']['devolvidos'] === 1, 'November returns');
expect($flow['2026-11']['saldo_pendente'] === 3, 'November backlog');
expect(!isset($flow['2026-12']), 'future month excluded');
expect(CatalogOperations::monthlyFlow(['itens' => []], '2026-11-08') === [], 'empty monthly flow');

$dupes = CatalogOperations::possibleDuplicates($data);
expect(count($dupes) === 2, 'two potential duplicate pairs');
expect($dupes[0]['primeiro_id'] === 'a-1', 'normalized names match');
expect($dupes[0]['segundo_id'] === 'a-2', 'matching pair id');
expect($dupes[0]['dias_entre_registros'] === 2, 'pair date gap');
expect($dupes[1]['primeiro_id'] === 'd-1' || $dupes[1]['primeiro_id'] === 'b-1', 'same category match');
expect(count(CatalogOperations::possibleDuplicates($data, 0)) === 0, 'zero day window');
expect(count(CatalogOperations::possibleDuplicates($data, 1)) === 1, 'one day window');
expect(count(CatalogOperations::possibleDuplicates(['itens' => []])) === 0, 'empty duplicate list');
$otherCategory = ['itens' => [entry('x-1', 'Chave', 'roupas', '2026-11-01'), entry('x-2', 'Chave', 'objetos', '2026-11-01')]];
expect(CatalogOperations::possibleDuplicates($otherCategory) === [], 'different category is not duplicate');

$health = CatalogOperations::inventoryHealth($data, '2026-11-08');
expect($health['total_registros'] === 6, 'health total');
expect($health['pendentes'] === 3, 'health pending');
expect($health['devolvidos'] === 2, 'health returned');
expect($health['pendentes_30_dias_ou_mais'] === 2, 'health old pending');
expect(count($health['possiveis_duplicidades']) === 2, 'health duplicates');
expect(isset($health['fluxo_mensal']['2026-11']), 'health monthly flow');
expect($data['itens'][0]['status'] === 'pendente', 'input unchanged');

expectInvalid(fn() => CatalogOperations::backlogAsOf($data, '2026-02-30'), 'bad snapshot date');
expectInvalid(fn() => CatalogOperations::staleItems($data, 'bad', 0), 'bad age date');
expectInvalid(fn() => CatalogOperations::staleItems($data, '2026-11-08', -1), 'negative age threshold');
expectInvalid(fn() => CatalogOperations::staleItems($data, '2026-11-08', 36501), 'excessive age threshold');
expectInvalid(fn() => CatalogOperations::possibleDuplicates($data, -1), 'negative duplicate window');
expectInvalid(fn() => CatalogOperations::possibleDuplicates($data, 366), 'excessive duplicate window');
expectInvalid(fn() => CatalogOperations::monthlyFlow($data, '2026-00-01'), 'invalid month');
expectInvalid(fn() => CatalogOperations::inventoryHealth(['itens' => [['id' => 'bad']]], '2026-11-08'), 'corrupt catalog');

if ($errors !== []) {
    foreach ($errors as $error) fwrite(STDERR, "FAIL: $error\n");
    fwrite(STDERR, count($errors) . " failures in $checks checks\n");
    exit(1);
}
echo "PASS: $checks operational checks\n";
