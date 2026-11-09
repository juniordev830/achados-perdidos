<?php
declare(strict_types=1);
require_once __DIR__ . '/CatalogReports.php';

/** Operational diagnostics; returns suggestions and never mutates a catalog. */
final class CatalogOperations
{
    public static function backlogAsOf(array $data, string $asOf): array
    {
        CatalogReports::validate($data);
        self::requireDate($asOf);
        $open = [];
        $returned = 0;
        $notYetFound = 0;
        foreach ($data['itens'] as $item) {
            if ($item['encontrado_em'] > $asOf) {
                $notYetFound++;
                continue;
            }
            if (($item['devolvido_em'] ?? null) !== null && $item['devolvido_em'] <= $asOf) {
                $returned++;
                continue;
            }
            $open[] = $item;
        }
        return [
            'data_referencia' => $asOf,
            'pendentes_na_data' => count($open),
            'devolvidos_ate_a_data' => $returned,
            'encontrados_depois_da_data' => $notYetFound,
            'ids_pendentes' => array_column($open, 'id'),
        ];
    }

    public static function staleItems(array $data, string $asOf, int $minimumDays = 30): array
    {
        CatalogReports::validate($data);
        self::requireDate($asOf);
        if ($minimumDays < 0 || $minimumDays > 36500) {
            throw new InvalidArgumentException('Invalid age threshold');
        }
        $reference = new DateTimeImmutable($asOf);
        $results = [];
        foreach ($data['itens'] as $item) {
            if ($item['encontrado_em'] > $asOf) continue;
            if (isset($item['devolvido_em']) && $item['devolvido_em'] <= $asOf) continue;
            $age = (int) (new DateTimeImmutable($item['encontrado_em']))->diff($reference)->days;
            if ($age < $minimumDays) continue;
            $results[] = [
                'id' => $item['id'],
                'categoria' => $item['categoria'],
                'encontrado_em' => $item['encontrado_em'],
                'dias_sem_devolucao' => $age,
            ];
        }
        usort($results, static fn(array $a, array $b): int =>
            [$b['dias_sem_devolucao'], $a['id']] <=> [$a['dias_sem_devolucao'], $b['id']]);
        return $results;
    }

    public static function agingBuckets(array $data, string $asOf): array
    {
        $items = self::staleItems($data, $asOf, 0);
        $buckets = ['0-7' => 0, '8-30' => 0, '31-90' => 0, '91+' => 0];
        foreach ($items as $item) {
            $days = $item['dias_sem_devolucao'];
            $bucket = match (true) {
                $days <= 7 => '0-7',
                $days <= 30 => '8-30',
                $days <= 90 => '31-90',
                default => '91+',
            };
            $buckets[$bucket]++;
        }
        return ['data_referencia' => $asOf, 'total_pendentes' => count($items), 'faixas_dias' => $buckets];
    }

    public static function monthlyFlow(array $data, string $asOf): array
    {
        CatalogReports::validate($data);
        self::requireDate($asOf);
        $months = [];
        foreach ($data['itens'] as $item) {
            if ($item['encontrado_em'] > $asOf) continue;
            $foundMonth = substr($item['encontrado_em'], 0, 7);
            $months[$foundMonth] ??= ['encontrados' => 0, 'devolvidos' => 0];
            $months[$foundMonth]['encontrados']++;
            if (isset($item['devolvido_em']) && $item['devolvido_em'] <= $asOf) {
                $returnedMonth = substr($item['devolvido_em'], 0, 7);
                $months[$returnedMonth] ??= ['encontrados' => 0, 'devolvidos' => 0];
                $months[$returnedMonth]['devolvidos']++;
            }
        }
        ksort($months);
        $backlog = 0;
        foreach ($months as &$month) {
            $backlog += $month['encontrados'] - $month['devolvidos'];
            $month['saldo_pendente'] = $backlog;
        }
        unset($month);
        return $months;
    }

    public static function possibleDuplicates(array $data, int $windowDays = 7): array
    {
        CatalogReports::validate($data);
        if ($windowDays < 0 || $windowDays > 365) {
            throw new InvalidArgumentException('Invalid duplicate window');
        }
        $groups = [];
        foreach ($data['itens'] as $item) {
            $name = self::normalize($item['objeto']);
            $key = $item['categoria'] . "\0" . $name;
            $groups[$key][] = $item;
        }
        $pairs = [];
        foreach ($groups as $group) {
            usort($group, static fn(array $a, array $b): int =>
                [$a['encontrado_em'], $a['id']] <=> [$b['encontrado_em'], $b['id']]);
            $count = count($group);
            for ($i = 0; $i < $count; $i++) {
                $start = new DateTimeImmutable($group[$i]['encontrado_em']);
                for ($j = $i + 1; $j < $count; $j++) {
                    $end = new DateTimeImmutable($group[$j]['encontrado_em']);
                    $days = (int) $start->diff($end)->days;
                    if ($days > $windowDays) break;
                    $pairs[] = [
                        'primeiro_id' => $group[$i]['id'],
                        'segundo_id' => $group[$j]['id'],
                        'categoria' => $group[$i]['categoria'],
                        'dias_entre_registros' => $days,
                        'aviso' => 'Possivel duplicidade; requer revisao humana',
                    ];
                    if (count($pairs) >= 500) return $pairs;
                }
            }
        }
        usort($pairs, static fn(array $a, array $b): int =>
            [$a['primeiro_id'], $a['segundo_id']] <=> [$b['primeiro_id'], $b['segundo_id']]);
        return $pairs;
    }

    public static function inventoryHealth(array $data, string $asOf): array
    {
        $backlog = self::backlogAsOf($data, $asOf);
        $aging = self::agingBuckets($data, $asOf);
        $stale = self::staleItems($data, $asOf, 30);
        return [
            'data_referencia' => $asOf,
            'total_registros' => count($data['itens']),
            'pendentes' => $backlog['pendentes_na_data'],
            'devolvidos' => $backlog['devolvidos_ate_a_data'],
            'pendentes_30_dias_ou_mais' => count($stale),
            'faixas_dias' => $aging['faixas_dias'],
            'possiveis_duplicidades' => self::possibleDuplicates($data),
            'fluxo_mensal' => self::monthlyFlow($data, $asOf),
        ];
    }

    private static function normalize(string $name): string
    {
        $lower = strtolower(trim($name));
        return preg_replace('/\s+/', ' ', $lower) ?? $lower;
    }

    private static function requireDate(string $date): void
    {
        if (!CatalogReports::validDate($date)) {
            throw new InvalidArgumentException('Invalid reference date');
        }
    }
}
