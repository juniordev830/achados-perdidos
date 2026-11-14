<?php
declare(strict_types=1);

// This test launches separate CLI processes: a single-process unit test cannot
// detect lost updates caused by concurrent read-modify-write operations.
function runParallel(array $commands): array
{
    $running = [];
    foreach ($commands as $command) {
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start subprocess');
        }
        fclose($pipes[0]);
        $running[] = [$process, $pipes];
    }

    $results = [];
    foreach ($running as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = [proc_close($process), $out, $err];
    }
    return $results;
}

if (!function_exists('proc_open')) {
    throw new RuntimeException('proc_open is required to test concurrent CLI writes');
}

$folder = sys_get_temp_dir() . '/achados-lock-' . bin2hex(random_bytes(8));
if (!mkdir($folder, 0700)) {
    throw new RuntimeException('Could not create private temp directory');
}
$file = $folder . '/catalog.json';
$entrypoint = __DIR__ . '/../main.php';

try {
    $commands = [];
    for ($i = 1; $i <= 24; $i++) {
        $commands[] = [
            PHP_BINARY, $entrypoint, 'adicionar', $file, 'item-' . $i,
            'Objeto de teste ' . $i, 'objetos', '2026-11-14',
        ];
    }
    foreach (runParallel($commands) as [$status, $out, $err]) {
        if ($status !== 0) {
            throw new RuntimeException('Concurrent insert failed: ' . $err);
        }
    }
    $data = json_decode((string) file_get_contents($file), true, 128, JSON_THROW_ON_ERROR);
    $ids = array_column($data['itens'], 'id');
    if (count($ids) !== 24 || count(array_unique($ids)) !== 24) {
        throw new RuntimeException('Concurrent writes lost or duplicated catalog entries');
    }

    $returns = array_fill(0, 6, [
        PHP_BINARY, $entrypoint, 'devolver', $file, 'item-1', '2026-11-14',
    ]);
    $successes = 0;
    foreach (runParallel($returns) as [$status, $out, $err]) {
        if ($status === 0) {
            $successes++;
        } elseif ($status !== 2) {
            throw new RuntimeException('Unexpected return exit status ' . $status . ': ' . $err);
        }
    }
    $data = json_decode((string) file_get_contents($file), true, 128, JSON_THROW_ON_ERROR);
    $returned = array_values(array_filter($data['itens'], static fn(array $item): bool =>
        $item['id'] === 'item-1'))[0];
    if ($successes !== 1 || $returned['status'] !== 'devolvido' ||
        count($returned['historico']) !== 2 || count($data['itens']) !== 24) {
        throw new RuntimeException('Concurrent returns did not preserve a single transition');
    }
    echo "PASS: 24 concurrent inserts and 6 competing returns\n";
} finally {
    if (file_exists($file)) unlink($file);
    if (file_exists($file . '.lock')) unlink($file . '.lock');
    rmdir($folder);
}
