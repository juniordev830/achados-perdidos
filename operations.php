<?php
declare(strict_types=1);
require_once __DIR__ . '/src/Operations.php';

function operationsUsage(): string
{
    return <<<'HELP'
Uso:
  php operations.php diagnostico ARQUIVO [--referencia=AAAA-MM-DD]
  php operations.php pendencias ARQUIVO [--referencia=AAAA-MM-DD] [--dias=30]
  php operations.php faixas ARQUIVO [--referencia=AAAA-MM-DD]
  php operations.php fluxo ARQUIVO [--referencia=AAAA-MM-DD]
  php operations.php duplicidades ARQUIVO [--janela=7]

Somente leitura. Duplicidades sao sugestoes e exigem revisao humana.
HELP;
}

function operationsOptions(array $args): array
{
    $allowed = ['referencia', 'dias', 'janela'];
    $options = [];
    foreach ($args as $arg) {
        if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
            throw new InvalidArgumentException('Opcao invalida: ' . $arg);
        }
        [$key, $value] = explode('=', substr($arg, 2), 2);
        if (!in_array($key, $allowed, true) || isset($options[$key])) {
            throw new InvalidArgumentException('Opcao desconhecida ou duplicada: ' . $key);
        }
        $options[$key] = $value;
    }
    return $options;
}

function operationsResult(string $command, array $data, array $options): array
{
    $allowed = match ($command) {
        'diagnostico', 'faixas', 'fluxo' => ['referencia'],
        'pendencias' => ['referencia', 'dias'],
        'duplicidades' => ['janela'],
        default => throw new InvalidArgumentException('Comando desconhecido'),
    };
    if (array_diff(array_keys($options), $allowed) !== []) {
        throw new InvalidArgumentException('Opcao nao permitida para este comando');
    }
    $asOf = $options['referencia'] ?? date('Y-m-d');
    foreach (['dias', 'janela'] as $key) {
        if (isset($options[$key]) && !preg_match('/^(0|[1-9][0-9]{0,4})$/D', $options[$key])) {
            throw new InvalidArgumentException('Numero invalido: ' . $key);
        }
    }
    return match ($command) {
        'diagnostico' => CatalogOperations::inventoryHealth($data, $asOf),
        'pendencias' => CatalogOperations::staleItems($data, $asOf, (int) ($options['dias'] ?? 30)),
        'faixas' => CatalogOperations::agingBuckets($data, $asOf),
        'fluxo' => CatalogOperations::monthlyFlow($data, $asOf),
        'duplicidades' => CatalogOperations::possibleDuplicates($data, (int) ($options['janela'] ?? 7)),
    };
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if (in_array($argv[1] ?? '', ['-h', '--help'], true)) {
            echo operationsUsage() . "\n";
            exit(0);
        }
        $command = $argv[1] ?? '';
        $file = $argv[2] ?? '';
        if ($file === '') throw new InvalidArgumentException(operationsUsage());
        $data = CatalogReports::read($file);
        $result = operationsResult($command, $data, operationsOptions(array_slice($argv, 3)));
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
        exit(2);
    }
}
