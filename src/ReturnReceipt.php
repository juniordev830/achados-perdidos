<?php
declare(strict_types=1);

/**
 * Recibo interno de devolução sem identificação de pessoas.
 * Isto NÃO é um comprovante assinado ou garantia contra fraude.
 */
final class ReturnReceipt {
    private static function validDate(string $date): bool {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    public static function build(array $catalogue, string $id): array {
        $items = $catalogue['itens'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new InvalidArgumentException('Catálogo inválido');
        }
        foreach ($items as $item) {
            if (!is_array($item) || ($item['id'] ?? null) !== $id) continue;
            if (($item['status'] ?? null) !== 'devolvido') {
                throw new InvalidArgumentException('Objeto ainda não foi devolvido');
            }
            $found = $item['encontrado_em'] ?? null;
            $returned = $item['devolvido_em'] ?? null;
            if (!is_string($found) || !is_string($returned) ||
                !self::validDate($found) || !self::validDate($returned) ||
                $returned < $found) {
                throw new InvalidArgumentException('Datas inconsistentes');
            }
            $events = $item['historico'] ?? [];
            if (!is_array($events) || count(array_filter($events, static fn($e): bool =>
                is_array($e) && ($e['evento'] ?? null) === 'devolvido' && ($e['data'] ?? null) === $returned)) !== 1) {
                throw new InvalidArgumentException('Histórico de devolução inconsistente');
            }
            return [
                'item_id' => $id,
                'status' => 'devolvido',
                'encontrado_em' => $found,
                'devolvido_em' => $returned,
                'referencia' => substr(hash('sha256', $id . '|' . $found . '|' . $returned), 0, 16),
                'aviso' => 'Registro interno sem identificação ou assinatura de terceiros'
            ];
        }
        throw new InvalidArgumentException('Item não encontrado');
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) &&
    realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        if ($argc !== 3 || !is_file($argv[1]) || filesize($argv[1]) > 2_000_000) {
            throw new InvalidArgumentException('Uso: php src/ReturnReceipt.php CATALOGO.json ID');
        }
        $data = json_decode((string)file_get_contents($argv[1]), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new InvalidArgumentException('Catálogo inválido');
        echo json_encode(ReturnReceipt::build($data, $argv[2]),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
        exit(2);
    }
}
