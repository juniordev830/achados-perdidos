<?php
declare(strict_types=1);

namespace AchadosPerdidos;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Catálogo local em memória. Não inclui identificação de proprietários e
 * não publica dados automaticamente. Descrições devem ser genéricas.
 */
final class Catalogo
{
    /** @var array<string, array<string, string|null>> */
    private array $objetos = [];

    /** @return array<string, string|null> */
    public function cadastrar(string $id, string $categoria, string $descricao, string $data): array
    {
        $id = trim($id);
        $categoria = trim($categoria);
        $descricao = trim($descricao);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $id)) {
            throw new InvalidArgumentException('ID deve conter 1 a 64 caracteres alfanuméricos, _ ou -.');
        }
        if ($categoria === '' || mb_strlen($categoria, 'UTF-8') > 60) {
            throw new InvalidArgumentException('Categoria deve ter entre 1 e 60 caracteres.');
        }
        if ($descricao === '' || mb_strlen($descricao, 'UTF-8') > 200) {
            throw new InvalidArgumentException('Descrição deve ter entre 1 e 200 caracteres.');
        }
        self::validarData($data);
        if (isset($this->objetos[$id])) {
            throw new DomainException('O ID informado já está cadastrado.');
        }

        $registro = [
            'id' => $id,
            'categoria' => $categoria,
            'descricao' => $descricao,
            'data_registro' => $data,
            'status' => 'pendente',
            'data_devolucao' => null,
        ];
        $this->objetos[$id] = $registro;
        return $registro;
    }

    /** @return array<string, string|null> */
    public function marcarDevolucao(string $id, string $data): array
    {
        self::validarData($data);
        if (!isset($this->objetos[$id])) {
            throw new DomainException('Objeto não encontrado.');
        }
        $registro = $this->objetos[$id];
        if ($registro['status'] === 'devolvido') {
            throw new DomainException('Este objeto já foi devolvido.');
        }
        if ($data < $registro['data_registro']) {
            throw new InvalidArgumentException('A data de devolução não pode ser anterior ao registro.');
        }
        $registro['status'] = 'devolvido';
        $registro['data_devolucao'] = $data;
        $this->objetos[$id] = $registro;
        return $registro;
    }

    /** @return array<string, string|null> */
    public function consultar(string $id): array
    {
        if (!isset($this->objetos[$id])) {
            throw new DomainException('Objeto não encontrado.');
        }
        return $this->objetos[$id];
    }

    /** @return list<array<string, string|null>> */
    public function pendentes(?string $categoria = null, ?string $de = null, ?string $ate = null): array
    {
        if ($de !== null) {
            self::validarData($de);
        }
        if ($ate !== null) {
            self::validarData($ate);
        }
        if ($de !== null && $ate !== null && $de > $ate) {
            throw new InvalidArgumentException('Data inicial não pode ser posterior à final.');
        }
        $filtrados = array_values(array_filter(
            $this->objetos,
            static function (array $registro) use ($categoria, $de, $ate): bool {
                if ($registro['status'] !== 'pendente') {
                    return false;
                }
                if ($categoria !== null && strcasecmp($registro['categoria'], trim($categoria)) !== 0) {
                    return false;
                }
                if ($de !== null && $registro['data_registro'] < $de) {
                    return false;
                }
                return $ate === null || $registro['data_registro'] <= $ate;
            }
        ));
        usort($filtrados, static fn (array $a, array $b): int =>
            [$a['data_registro'], $a['id']] <=> [$b['data_registro'], $b['id']]
        );
        return $filtrados;
    }

    private static function validarData(string $data): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data)) {
            throw new InvalidArgumentException('Data deve estar no formato AAAA-MM-DD.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($parsed === false || $parsed->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('Data inválida.');
        }
    }
}
