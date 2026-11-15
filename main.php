<?php
declare(strict_types=1);

function dateIsValid(string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function items(array $data): array {
    if (!isset($data['itens']) || !is_array($data['itens']) || !array_is_list($data['itens'])) {
        throw new InvalidArgumentException('Catalogo precisa de lista de itens');
    }
    return $data['itens'];
}

function addFound(array $data, string $id, string $name, string $category, string $foundAt): array {
    if (!preg_match('/^[a-z0-9-]{1,50}$/', $id) ||
        trim($name)==='' || strlen($name)>80 ||
        !preg_match('/^[a-z-]{2,30}$/', $category) ||
        !dateIsValid($foundAt)) {
        throw new InvalidArgumentException('Identificador, objeto, categoria ou data invalidos');
    }
    foreach (items($data) as $item) {
        if (($item['id'] ?? null) === $id) throw new InvalidArgumentException('ID duplicado');
    }
    $data['itens'][] = [
        'id' => $id, 'objeto' => trim($name), 'categoria' => $category,
        'encontrado_em' => $foundAt, 'status' => 'pendente',
        'historico' => [['evento' => 'encontrado', 'data' => $foundAt]]
    ];
    return $data;
}

function markReturned(array $data, string $id, string $returnedAt): array {
    if (!dateIsValid($returnedAt)) throw new InvalidArgumentException('Data invalida');
    foreach (items($data) as $index => $item) {
        if (($item['id'] ?? null) !== $id) continue;
        if (($item['status'] ?? null) !== 'pendente') throw new InvalidArgumentException('Item ja devolvido');
        if ($returnedAt < $item['encontrado_em']) throw new InvalidArgumentException('Devolucao anterior ao encontro');
        $data['itens'][$index]['status'] = 'devolvido';
        $data['itens'][$index]['devolvido_em'] = $returnedAt;
        $data['itens'][$index]['historico'][] = ['evento' => 'devolvido', 'data' => $returnedAt];
        return $data;
    }
    throw new InvalidArgumentException('Item nao encontrado');
}

function pending(array $data, string $category='todos'): array {
    $out = array_values(array_filter(items($data), static fn($item): bool =>
        ($item['status'] ?? null) === 'pendente' &&
        ($category === 'todos' || ($item['categoria'] ?? null) === $category)));
    usort($out, static fn($left,$right): int =>
        [$left['encontrado_em'], $left['id']] <=> [$right['encontrado_em'], $right['id']]);
    return $out;
}

function readData(string $file): array {
    if (!is_file($file)) return ['itens' => []];
    $size = filesize($file);
    if ($size === false || $size > 2_000_000) throw new RuntimeException('Arquivo maior que 2 MB');
    $data = json_decode((string)file_get_contents($file), true, 128, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException('JSON de catalogo invalido');
    items($data);
    return $data;
}

function saveData(string $file, array $data): void {
    $directory = dirname($file);
    if (!is_dir($directory)) throw new RuntimeException('Pasta de destino nao existe');
    $temp = tempnam($directory, '.achados-');
    if ($temp === false) throw new RuntimeException('Falha ao criar temporario');
    try {
        $json = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !rename($temp, $file)) {
            throw new RuntimeException('Falha de gravacao');
        }
    } finally {
        if (file_exists($temp)) unlink($temp);
    }
}


// A lock file remains stable while the JSON is atomically renamed. Locking
// the JSON itself would not protect readers using the replacement inode.
function updateDataFile(string $file, callable $change): void {
    $lockPath = $file . '.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) throw new RuntimeException('Falha ao abrir trava do catalogo');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Falha ao bloquear catalogo');
        try {
            $data = readData($file);
            $updated = $change($data);
            saveData($file, $updated);
        } finally {
            flock($lock, LOCK_UN);
        }
    } finally {
        fclose($lock);
    }
}

function selfTest(): void {
    $empty = ['itens' => []];
    $one = addFound($empty, 'id-1', 'Guarda-chuva', 'acessorios', '2026-11-07');
    $two = addFound($one, 'id-2', 'Garrafa', 'objetos', '2026-11-08');
    if (count(pending($two)) !== 2 || count(pending($two,'objetos')) !== 1) throw new RuntimeException('Filtro incorreto');
    $returned = markReturned($two, 'id-1', '2026-11-08');
    if (count(pending($returned)) !== 1 || count($returned['itens'][0]['historico']) !== 2) throw new RuntimeException('Devolucao incorreta');
    if (count(pending($two)) !== 2) throw new RuntimeException('Original alterado');
    try { markReturned($returned, 'id-1', '2026-11-08'); throw new RuntimeException('Falhou'); }
    catch (InvalidArgumentException $e) {}
    try { addFound($two, 'id-1', 'Outro', 'acessorios', '2026-11-08'); throw new RuntimeException('Falhou'); }
    catch (InvalidArgumentException $e) {}
}

try {
    if (($argv[1] ?? '') === '--self-test') {
        selfTest(); echo "5 verificacoes OK\n"; exit(0);
    }
    $command = $argv[1] ?? '';
    $file = $argv[2] ?? '';
    if ($file === '') throw new InvalidArgumentException('Informe o arquivo JSON');
    if ($command === 'adicionar' && count($argv) === 7) {
        updateDataFile($file, static fn(array $data): array =>
            addFound($data, $argv[3], $argv[4], $argv[5], $argv[6]));
    } elseif ($command === 'devolver' && count($argv) === 5) {
        updateDataFile($file, static fn(array $data): array =>
            markReturned($data, $argv[3], $argv[4]));
    } elseif ($command === 'pendentes' && (count($argv) === 3 || count($argv) === 4)) {
        echo json_encode(pending(readData($file), $argv[3] ?? 'todos'), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    } elseif ($command === 'todos' && count($argv) === 3) {
        echo json_encode(items(readData($file)), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    } else {
        throw new InvalidArgumentException('Uso: php main.php adicionar ARQUIVO ID OBJETO CATEGORIA DATA | devolver ARQUIVO ID DATA | pendentes ARQUIVO [CATEGORIA] | todos ARQUIVO');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: '.$e->getMessage()."\n");
    exit(2);
}
