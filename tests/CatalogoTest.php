<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Catalogo.php';

use AchadosPerdidos\Catalogo;

function mesmo(mixed $esperado, mixed $atual, string $mensagem): void
{
    if ($esperado !== $atual) {
        throw new RuntimeException($mensagem . ' esperado=' . var_export($esperado, true)
            . ' atual=' . var_export($atual, true));
    }
}

function falhaEsperada(callable $acao, string $tipo): void
{
    try {
        $acao();
    } catch (Throwable $error) {
        if (!$error instanceof $tipo) {
            throw new RuntimeException("Erro incorreto: " . get_class($error), 0, $error);
        }
        return;
    }
    throw new RuntimeException("Esperava exceção: {$tipo}");
}

$catalogo = new Catalogo();
$catalogo->cadastrar('item-001', 'Eletrônicos', 'Cabo USB preto', '2026-11-01');
$catalogo->cadastrar('item-002', 'Roupas', 'Casaco azul', '2026-11-02');
mesmo(2, count($catalogo->pendentes()), 'Itens inicialmente pendentes');
mesmo(1, count($catalogo->pendentes('roupas')), 'Filtro de categoria sem distinção de caixa');

falhaEsperada(
    fn () => $catalogo->cadastrar('item-001', 'Roupas', 'Repetido', '2026-11-03'),
    DomainException::class
);
falhaEsperada(
    fn () => $catalogo->cadastrar('item-003', 'Roupas', 'Teste', '2026-02-30'),
    InvalidArgumentException::class
);
falhaEsperada(
    fn () => $catalogo->marcarDevolucao('item-001', '2026-10-31'),
    InvalidArgumentException::class
);
mesmo('pendente', $catalogo->consultar('item-001')['status'], 'Tentativa inválida não altera o registro');

$devolvido = $catalogo->marcarDevolucao('item-001', '2026-11-04');
mesmo('devolvido', $devolvido['status'], 'Status de devolução');
mesmo('2026-11-04', $devolvido['data_devolucao'], 'Data mantida no histórico');
mesmo(1, count($catalogo->pendentes()), 'Devolvido sai da lista de pendentes');
mesmo('devolvido', $catalogo->consultar('item-001')['status'], 'Registro interno preservado');
falhaEsperada(
    fn () => $catalogo->marcarDevolucao('item-001', '2026-11-05'),
    DomainException::class
);
falhaEsperada(
    fn () => $catalogo->marcarDevolucao('nao-existe', '2026-11-05'),
    DomainException::class
);
falhaEsperada(
    fn () => $catalogo->cadastrar('invalido!', 'Roupas', 'Teste', '2026-11-05'),
    InvalidArgumentException::class
);

echo "Todos os testes do catálogo passaram.\n";
