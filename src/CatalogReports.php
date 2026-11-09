<?php
declare(strict_types=1);

/** Read-only, privacy-conscious reporting for the local lost-and-found catalog. */
final class CatalogReports
{
    private const MAX_BYTES = 2_000_000;
    private const MAX_ITEMS = 20_000;
    private const DATE_FORMAT = '!Y-m-d';

    public static function read(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Catalog file does not exist or cannot be read');
        }
        $size = filesize($file);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException('Catalog file exceeds 2 MB');
        }
        $json = file_get_contents($file);
        if ($json === false) {
            throw new RuntimeException('Unable to read catalog');
        }
        try {
            $data = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('Invalid catalog JSON', 0, $error);
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('Catalog root must be an object');
        }
        return self::validate($data);
    }

    public static function validate(array $data): array
    {
        if (!isset($data['itens']) || !is_array($data['itens']) || !array_is_list($data['itens'])) {
            throw new InvalidArgumentException('Catalog must contain a list of items');
        }
        if (count($data['itens']) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('Catalog exceeds item limit');
        }
        $seen = [];
        foreach ($data['itens'] as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException("Item $index is not an object");
            }
            foreach (['id', 'objeto', 'categoria', 'encontrado_em', 'status'] as $key) {
                if (!isset($item[$key]) || !is_string($item[$key])) {
                    throw new InvalidArgumentException("Item $index missing $key");
                }
            }
            $id = $item['id'];
            if (!preg_match('/^[a-z0-9-]{1,50}$/D', $id) || isset($seen[$id])) {
                throw new InvalidArgumentException("Duplicate or invalid item ID at $index");
            }
            $seen[$id] = true;
            if (trim($item['objeto']) === '' || strlen($item['objeto']) > 80) {
                throw new InvalidArgumentException("Invalid object description at $index");
            }
            if (!preg_match('/^[a-z-]{2,30}$/D', $item['categoria'])) {
                throw new InvalidArgumentException("Invalid category at $index");
            }
            if (!self::validDate($item['encontrado_em'])) {
                throw new InvalidArgumentException("Invalid found date at $index");
            }
            if (!in_array($item['status'], ['pendente', 'devolvido'], true)) {
                throw new InvalidArgumentException("Invalid status at $index");
            }
            if ($item['status'] === 'devolvido') {
                if (!isset($item['devolvido_em']) || !is_string($item['devolvido_em']) ||
                    !self::validDate($item['devolvido_em']) || $item['devolvido_em'] < $item['encontrado_em']) {
                    throw new InvalidArgumentException("Invalid return date at $index");
                }
            } elseif (array_key_exists('devolvido_em', $item)) {
                throw new InvalidArgumentException("Pending item cannot have return date at $index");
            }
            if (!isset($item['historico']) || !is_array($item['historico']) || !array_is_list($item['historico'])) {
                throw new InvalidArgumentException("Invalid history at $index");
            }
            $expected = $item['status'] === 'devolvido' ? 2 : 1;
            if (count($item['historico']) !== $expected) {
                throw new InvalidArgumentException("Unexpected history length at $index");
            }
            foreach ($item['historico'] as $step => $event) {
                $type = $step === 0 ? 'encontrado' : 'devolvido';
                $date = $step === 0 ? $item['encontrado_em'] : $item['devolvido_em'];
                if (!is_array($event) || ($event['evento'] ?? null) !== $type || ($event['data'] ?? null) !== $date) {
                    throw new InvalidArgumentException("Inconsistent history at $index");
                }
            }
        }
        return $data;
    }

    public static function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    public static function search(array $data, array $filters = []): array
    {
        self::validate($data);
        $status = $filters['status'] ?? 'todos';
        if (!in_array($status, ['todos', 'pendente', 'devolvido'], true)) {
            throw new InvalidArgumentException('Invalid status filter');
        }
        $category = $filters['categoria'] ?? null;
        if ($category !== null && (!is_string($category) || !preg_match('/^[a-z-]{2,30}$/D', $category))) {
            throw new InvalidArgumentException('Invalid category filter');
        }
        $from = $filters['desde'] ?? null;
        $until = $filters['ate'] ?? null;
        foreach ([$from, $until] as $date) {
            if ($date !== null && (!is_string($date) || !self::validDate($date))) {
                throw new InvalidArgumentException('Invalid date filter');
            }
        }
        if ($from !== null && $until !== null && $from > $until) {
            throw new InvalidArgumentException('Start date must not exceed end date');
        }
        $term = $filters['busca'] ?? '';
        if (!is_string($term) || strlen($term) > 80) {
            throw new InvalidArgumentException('Invalid search term');
        }
        $page = self::positiveInt($filters['pagina'] ?? 1, 'pagina', 100000);
        $limit = self::positiveInt($filters['limite'] ?? 25, 'limite', 200);
        $sort = $filters['ordem'] ?? 'data';
        if (!in_array($sort, ['data', 'id', 'categoria'], true)) {
            throw new InvalidArgumentException('Invalid sort order');
        }
        $matched = [];
        foreach ($data['itens'] as $item) {
            if ($status !== 'todos' && $item['status'] !== $status) continue;
            if ($category !== null && $item['categoria'] !== $category) continue;
            if ($from !== null && $item['encontrado_em'] < $from) continue;
            if ($until !== null && $item['encontrado_em'] > $until) continue;
            if ($term !== '' && stripos($item['objeto'], $term) === false && stripos($item['id'], $term) === false) continue;
            $matched[] = $item;
        }
        usort($matched, static function (array $a, array $b) use ($sort): int {
            $primary = match ($sort) {
                'id' => $a['id'] <=> $b['id'],
                'categoria' => $a['categoria'] <=> $b['categoria'],
                default => $a['encontrado_em'] <=> $b['encontrado_em'],
            };
            return $primary ?: ($a['id'] <=> $b['id']);
        });
        $total = count($matched);
        return [
            'total' => $total,
            'pagina' => $page,
            'limite' => $limit,
            'paginas' => (int) ceil($total / $limit),
            'itens' => array_slice($matched, ($page - 1) * $limit, $limit),
        ];
    }

    public static function summary(array $data, string $asOf): array
    {
        self::validate($data);
        if (!self::validDate($asOf)) throw new InvalidArgumentException('Invalid report date');
        $categories = [];
        $months = [];
        $pendingAge = [];
        $returnedDays = [];
        $returned = 0;
        $pending = 0;
        $future = 0;
        $reference = new DateTimeImmutable($asOf);
        foreach ($data['itens'] as $item) {
            $category = $item['categoria'];
            $month = substr($item['encontrado_em'], 0, 7);
            $categories[$category] ??= ['pendente' => 0, 'devolvido' => 0];
            $months[$month] ??= ['pendente' => 0, 'devolvido' => 0];
            $status = $item['status'];
            $categories[$category][$status]++;
            $months[$month][$status]++;
            $found = new DateTimeImmutable($item['encontrado_em']);
            if ($found > $reference) $future++;
            if ($status === 'pendente') {
                $pending++;
                if ($found <= $reference) $pendingAge[] = (int) $found->diff($reference)->days;
            } else {
                $returned++;
                $returnedAt = new DateTimeImmutable($item['devolvido_em']);
                $returnedDays[] = (int) $found->diff($returnedAt)->days;
            }
        }
        ksort($categories);
        ksort($months);
        return [
            'data_referencia' => $asOf,
            'total' => count($data['itens']),
            'pendentes' => $pending,
            'devolvidos' => $returned,
            'taxa_devolucao_percentual' => count($data['itens']) === 0 ? 0.0 : round($returned * 100 / count($data['itens']), 2),
            'encontrados_no_futuro' => $future,
            'idade_media_pendentes_dias' => self::average($pendingAge),
            'tempo_medio_devolucao_dias' => self::average($returnedDays),
            'por_categoria' => $categories,
            'por_mes' => $months,
        ];
    }

    public static function timeline(array $data, ?string $from = null, ?string $until = null): array
    {
        self::validate($data);
        foreach ([$from, $until] as $date) {
            if ($date !== null && !self::validDate($date)) throw new InvalidArgumentException('Invalid timeline date');
        }
        if ($from !== null && $until !== null && $from > $until) {
            throw new InvalidArgumentException('Invalid timeline range');
        }
        $events = [];
        foreach ($data['itens'] as $item) {
            foreach ($item['historico'] as $event) {
                if ($from !== null && $event['data'] < $from) continue;
                if ($until !== null && $event['data'] > $until) continue;
                $events[] = [
                    'data' => $event['data'],
                    'evento' => $event['evento'],
                    'id' => $item['id'],
                    'categoria' => $item['categoria'],
                ];
            }
        }
        usort($events, static fn(array $a, array $b): int =>
            [$a['data'], $a['id'], $a['evento']] <=> [$b['data'], $b['id'], $b['evento']]);
        return $events;
    }

    public static function toCsv(array $items): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) throw new RuntimeException('Unable to open CSV stream');
        try {
            fputcsv($stream, ['id', 'objeto', 'categoria', 'encontrado_em', 'status', 'devolvido_em'], ',', '"', '');
            foreach ($items as $item) {
                $row = [];
                foreach (['id', 'objeto', 'categoria', 'encontrado_em', 'status', 'devolvido_em'] as $key) {
                    $value = $item[$key] ?? '';
                    if (!is_string($value)) throw new InvalidArgumentException('Invalid CSV value');
                    $row[] = self::safeCell($value);
                }
                fputcsv($stream, $row, ',', '"', '');
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            if ($content === false) throw new RuntimeException('Unable to read CSV stream');
            return $content;
        } finally {
            fclose($stream);
        }
    }

    private static function safeCell(string $value): string
    {
        // Spreadsheet programs can evaluate formulas after whitespace or control prefixes.
        if (preg_match('/^[\x00-\x20]*[=+@\-]/', $value)) return "'" . $value;
        return $value;
    }

    private static function positiveInt(mixed $value, string $name, int $maximum): int
    {
        if (is_int($value)) $number = $value;
        elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) && strlen($value) <= 8) {
            $number = (int) $value;
        } else throw new InvalidArgumentException("Invalid $name");
        if ($number < 1 || $number > $maximum) throw new InvalidArgumentException("Invalid $name");
        return $number;
    }

    private static function average(array $values): ?float
    {
        return $values === [] ? null : round(array_sum($values) / count($values), 2);
    }
}
