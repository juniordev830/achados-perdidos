<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/CatalogReports.php';
require_once __DIR__ . '/../reports.php';

$checks = 0;
$failures = [];
function check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) $failures[] = $message;
}
function rejects(callable $callback, string $message): void
{
    global $checks, $failures;
    $checks++;
    try {
        $callback();
        $failures[] = $message . ' (accepted invalid input)';
    } catch (InvalidArgumentException|RuntimeException|JsonException $expected) {
        // Expected rejection.
    }
}
function fixture(): array
{
    return ['itens' => [
        [
            'id' => 'chave-1', 'objeto' => 'Chave azul', 'categoria' => 'objetos',
            'encontrado_em' => '2026-11-01', 'status' => 'pendente',
            'historico' => [['evento' => 'encontrado', 'data' => '2026-11-01']],
        ],
        [
            'id' => 'casaco-2', 'objeto' => 'Casaco cinza', 'categoria' => 'roupas',
            'encontrado_em' => '2026-10-30', 'status' => 'devolvido',
            'devolvido_em' => '2026-11-02',
            'historico' => [
                ['evento' => 'encontrado', 'data' => '2026-10-30'],
                ['evento' => 'devolvido', 'data' => '2026-11-02'],
            ],
        ],
        [
            'id' => 'garrafa-3', 'objeto' => 'Garrafa verde', 'categoria' => 'objetos',
            'encontrado_em' => '2026-11-04', 'status' => 'pendente',
            'historico' => [['evento' => 'encontrado', 'data' => '2026-11-04']],
        ],
    ]];
}

$data = fixture();
check(CatalogReports::validate($data) === $data, 'valid catalog preserved');
check(CatalogReports::validDate('2024-02-29'), 'leap day accepted');
check(!CatalogReports::validDate('2025-02-29'), 'invalid leap day rejected');
check(!CatalogReports::validDate('2026-13-01'), 'invalid month rejected');
check(!CatalogReports::validDate('2026-1-01'), 'non-ISO date rejected');

$all = CatalogReports::search($data);
check($all['total'] === 3, 'search counts all items');
check($all['itens'][0]['id'] === 'casaco-2', 'search sorts by date');
check($all['paginas'] === 1, 'page count');
check(CatalogReports::search($data, ['status' => 'pendente'])['total'] === 2, 'pending filter');
check(CatalogReports::search($data, ['status' => 'devolvido'])['total'] === 1, 'returned filter');
check(CatalogReports::search($data, ['categoria' => 'objetos'])['total'] === 2, 'category filter');
check(CatalogReports::search($data, ['desde' => '2026-11-02'])['total'] === 1, 'start date filter');
check(CatalogReports::search($data, ['ate' => '2026-11-01'])['total'] === 2, 'end date filter');
check(CatalogReports::search($data, ['busca' => 'CHAVE'])['total'] === 1, 'case insensitive search');
check(CatalogReports::search($data, ['busca' => 'garrafa-3'])['total'] === 1, 'search by ID');
check(CatalogReports::search($data, ['busca' => 'sem-match'])['total'] === 0, 'empty search');
check(CatalogReports::search($data, ['pagina' => 2, 'limite' => 2])['itens'][0]['id'] === 'garrafa-3', 'second page');
check(CatalogReports::search($data, ['pagina' => 3, 'limite' => 2])['itens'] === [], 'out of range page');
check(CatalogReports::search($data, ['ordem' => 'id'])['itens'][0]['id'] === 'casaco-2', 'sort by id');
check(CatalogReports::search($data, ['ordem' => 'categoria'])['itens'][2]['categoria'] === 'roupas', 'sort by category');
check(CatalogReports::search($data, ['status' => 'pendente', 'categoria' => 'roupas'])['total'] === 0, 'combined filters');

foreach ([
    ['status' => 'cancelado'], ['categoria' => 'INVÁLIDA'],
    ['desde' => '2026-02-30'], ['ate' => 'yesterday'],
    ['desde' => '2026-11-02', 'ate' => '2026-11-01'],
    ['busca' => str_repeat('x', 81)], ['pagina' => 0],
    ['pagina' => '-1'], ['pagina' => '999999999'],
    ['limite' => 0], ['limite' => 201], ['limite' => 'abc'],
    ['ordem' => 'random'],
] as $invalid) {
    rejects(fn() => CatalogReports::search($data, $invalid), 'invalid search option: ' . json_encode($invalid));
}

$summary = CatalogReports::summary($data, '2026-11-08');
check($summary['total'] === 3, 'summary total');
check($summary['pendentes'] === 2, 'summary pending');
check($summary['devolvidos'] === 1, 'summary returned');
check($summary['taxa_devolucao_percentual'] === 33.33, 'return rate');
check($summary['idade_media_pendentes_dias'] === 5.5, 'pending age');
check($summary['tempo_medio_devolucao_dias'] === 3.0, 'return duration');
check($summary['por_categoria']['objetos']['pendente'] === 2, 'category breakdown');
check($summary['por_mes']['2026-10']['devolvido'] === 1, 'month breakdown');
check($summary['encontrados_no_futuro'] === 0, 'no future found dates');
check(CatalogReports::summary(['itens' => []], '2026-11-08')['taxa_devolucao_percentual'] === 0.0, 'empty summary rate');
check(CatalogReports::summary(['itens' => []], '2026-11-08')['idade_media_pendentes_dias'] === null, 'empty average');
check(CatalogReports::summary($data, '2026-10-01')['encontrados_no_futuro'] === 3, 'future count');
rejects(fn() => CatalogReports::summary($data, '2026-11-31'), 'invalid summary date');

$timeline = CatalogReports::timeline($data);
check(count($timeline) === 4, 'timeline includes returns');
check($timeline[0]['evento'] === 'encontrado', 'timeline first event');
check($timeline[3]['id'] === 'garrafa-3', 'timeline chronological order');
check(count(CatalogReports::timeline($data, '2026-11-02', '2026-11-02')) === 1, 'timeline date bounds');
check(CatalogReports::timeline($data, '2027-01-01') === [], 'timeline future empty');
rejects(fn() => CatalogReports::timeline($data, '2026-11-08', '2026-11-01'), 'invalid timeline interval');

$csv = CatalogReports::toCsv($all['itens']);
check(str_contains($csv, 'chave-1,"Chave azul",objetos'), 'CSV row');
check(substr_count($csv, "\n") === 4, 'CSV header plus rows');
$malicious = $data['itens'][0];
$malicious['objeto'] = "  =HYPERLINK(\"bad\")";
$csvInjection = CatalogReports::toCsv([$malicious]);
check(str_contains($csvInjection, "'  =HYPERLINK"), 'CSV formula neutralized');
foreach (['+1+1', '-2+2', '@SUM(1)', "\t=1+1"] as $formula) {
    $malicious['objeto'] = $formula;
    check(str_contains(CatalogReports::toCsv([$malicious]), "'" . $formula), 'CSV formula prefix blocked');
}

$invalidData = $data;
$invalidData['itens'][] = $data['itens'][0];
rejects(fn() => CatalogReports::validate($invalidData), 'duplicate ID');
$invalidData = $data;
$invalidData['itens'][0]['status'] = 'inexistente';
rejects(fn() => CatalogReports::validate($invalidData), 'unknown status');
$invalidData = $data;
$invalidData['itens'][0]['devolvido_em'] = '2026-11-05';
rejects(fn() => CatalogReports::validate($invalidData), 'pending return date');
$invalidData = $data;
$invalidData['itens'][1]['devolvido_em'] = '2026-10-01';
rejects(fn() => CatalogReports::validate($invalidData), 'return before found');
$invalidData = $data;
$invalidData['itens'][1]['historico'][1]['data'] = '2026-11-03';
rejects(fn() => CatalogReports::validate($invalidData), 'tampered history');
$invalidData = $data;
$invalidData['itens'][0]['objeto'] = '';
rejects(fn() => CatalogReports::validate($invalidData), 'empty object');
$invalidData = $data;
$invalidData['itens'][0]['categoria'] = '99';
rejects(fn() => CatalogReports::validate($invalidData), 'invalid category');
rejects(fn() => CatalogReports::validate(['itens' => ['not-an-item']]), 'non-object item');
rejects(fn() => CatalogReports::validate(['itens' => 'string']), 'non-list items');
rejects(fn() => CatalogReports::validate(['itens' => array_fill(0, 20001, [])]), 'oversized catalog');

$options = parseReportOptions(['--status=pendente', '--limite=2']);
check($options['status'] === 'pendente', 'CLI parser status');
check($options['limite'] === '2', 'CLI parser limit');
rejects(fn() => parseReportOptions(['--status=pendente', '--status=todos']), 'duplicate CLI options');
rejects(fn() => parseReportOptions(['--surpresa=1']), 'unknown CLI option');
rejects(fn() => parseReportOptions(['--status']), 'malformed CLI option');
check(str_contains(reportOutput('lista', $data, ['status' => 'pendente']), 'chave-1'), 'CLI JSON listing');
check(str_contains(reportOutput('csv', $data, ['limite' => 1]), 'casaco-2'), 'CLI CSV export');
check(str_contains(reportOutput('resumo', $data, ['referencia' => '2026-11-08']), '33.33'), 'CLI summary');
check(str_contains(reportOutput('historico', $data, []), 'devolvido'), 'CLI history');
rejects(fn() => reportOutput('resumo', $data, ['limite' => '1']), 'CLI summary options');
rejects(fn() => reportOutput('historico', $data, ['status' => 'todos']), 'CLI history options');
rejects(fn() => reportOutput('csv', $data, ['referencia' => '2026-11-08']), 'CLI CSV options');
rejects(fn() => reportOutput('unknown', $data, []), 'unknown CLI command');

$file = tempnam(sys_get_temp_dir(), 'achados-test-');
if ($file === false) throw new RuntimeException('Cannot create test file');
try {
    file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
    check(CatalogReports::read($file) === $data, 'read local JSON');
    file_put_contents($file, '{invalid');
    rejects(fn() => CatalogReports::read($file), 'reject malformed JSON');
    file_put_contents($file, 'null');
    rejects(fn() => CatalogReports::read($file), 'reject JSON null');
    file_put_contents($file, str_repeat('x', 2_000_001));
    rejects(fn() => CatalogReports::read($file), 'reject large file');
} finally {
    unlink($file);
}
rejects(fn() => CatalogReports::read(sys_get_temp_dir() . '/achados-missing-very-unlikely.json'), 'reject missing file');

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: $failure\n");
    fwrite(STDERR, count($failures) . " failures in $checks checks\n");
    exit(1);
}
echo "PASS: $checks checks\n";
