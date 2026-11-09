<?php
declare(strict_types=1);
require_once __DIR__ . '/src/CatalogReports.php';

/** Read-only CLI: never modifies the source catalog. */
function reportUsage(): string
{
    return <<<'HELP'
Uso:
  php reports.php lista ARQUIVO [--status=pendente|devolvido|todos] [--categoria=NOME]
    [--desde=AAAA-MM-DD] [--ate=AAAA-MM-DD] [--busca=TEXTO]
    [--pagina=NUMERO] [--limite=NUMERO] [--ordem=data|id|categoria]
  php reports.php csv ARQUIVO [filtros acima]
  php reports.php resumo ARQUIVO [--referencia=AAAA-MM-DD]
  php reports.php historico ARQUIVO [--desde=AAAA-MM-DD] [--ate=AAAA-MM-DD]

Saida padrao: JSON, exceto csv. Redirecione CSV para um arquivo local privado.
O comando e somente leitura; dados de proprietarios nao devem ser registrados.
HELP;
}

function parseReportOptions(array $args): array
{
    $allowed = ['status', 'categoria', 'desde', 'ate', 'busca', 'pagina', 'limite', 'ordem', 'referencia'];
    $options = [];
    foreach ($args as $arg) {
        if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
            throw new InvalidArgumentException('Opcao invalida: ' . $arg);
        }
        [$name, $value] = explode('=', substr($arg, 2), 2);
        if (!in_array($name, $allowed, true) || array_key_exists($name, $options)) {
            throw new InvalidArgumentException('Opcao desconhecida ou repetida: ' . $name);
        }
        $options[$name] = $value;
    }
    return $options;
}

function reportOutput(string $command, array $data, array $options): string
{
    if ($command === 'lista' || $command === 'csv') {
        if (isset($options['referencia'])) {
            throw new InvalidArgumentException('Referencia somente para resumo');
        }
        $result = CatalogReports::search($data, $options);
        return $command === 'csv'
            ? CatalogReports::toCsv($result['itens'])
            : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
    if ($command === 'resumo') {
        if (array_diff(array_keys($options), ['referencia']) !== []) {
            throw new InvalidArgumentException('Resumo aceita apenas --referencia');
        }
        $result = CatalogReports::summary($data, $options['referencia'] ?? date('Y-m-d'));
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
    if ($command === 'historico') {
        if (array_diff(array_keys($options), ['desde', 'ate']) !== []) {
            throw new InvalidArgumentException('Historico aceita apenas datas');
        }
        $result = CatalogReports::timeline($data, $options['desde'] ?? null, $options['ate'] ?? null);
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
    throw new InvalidArgumentException('Comando desconhecido: ' . $command);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if (($argv[1] ?? '') === '--help' || ($argv[1] ?? '') === '-h') {
            echo reportUsage() . "\n";
            exit(0);
        }
        $command = $argv[1] ?? '';
        $file = $argv[2] ?? '';
        if ($file === '' || !in_array($command, ['lista', 'csv', 'resumo', 'historico'], true)) {
            throw new InvalidArgumentException(reportUsage());
        }
        $options = parseReportOptions(array_slice($argv, 3));
        echo reportOutput($command, CatalogReports::read($file), $options);
    } catch (Throwable $error) {
        fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
        exit(2);
    }
}
