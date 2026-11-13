# Achados e Perdidos

MVP em PHP 8.2+ para registrar objetos encontrados em ambientes comunitários sem incluir dados pessoais de proprietários.

## Funcionalidades

- Cadastro de objetos com identificador, descrição genérica, categoria e data.
- Lista de pendentes, registro de devoluções e histórico preservado.
- Validação de IDs, datas e devoluções; persistência JSON por substituição atômica.
- Relatórios somente leitura com filtros por status, categoria, período e texto, além de ordenação e paginação.
- Indicadores de pendências, devoluções, taxa de devolução, idade das pendências e distribuições por categoria/mês.
- Histórico cronológico de encontros e devoluções.
- CSV com neutralização de células que podem ser interpretadas como fórmulas em planilhas.

## Cadastro e devolução

O comando main.php continua responsável pela escrita e é compatível com o arquivo JSON anterior.

    php main.php adicionar dados.json chaves-1 "Chaveiro simples" objetos 2026-11-08
    php main.php adicionar dados.json roupas-1 "Blusa azul" roupas 2026-11-08
    php main.php pendentes dados.json
    php main.php devolver dados.json chaves-1 2026-11-08
    php main.php todos dados.json

## Relatórios locais

O comando reports.php lê o mesmo arquivo JSON sem modificar o catálogo:

    php reports.php lista dados.json --status=pendente --categoria=roupas
    php reports.php lista dados.json --desde=2026-11-01 --ate=2026-11-30 --pagina=1 --limite=20
    php reports.php lista dados.json --busca=chave --ordem=id
    php reports.php resumo dados.json --referencia=2026-11-08
    php reports.php historico dados.json --desde=2026-11-01
    php reports.php csv dados.json --status=devolvido > devolvidos.csv
    php reports.php --help

A saída é JSON, exceto CSV. A exportação CSV inclui somente a página selecionada; use --limite=200 e paginação para conjuntos maiores. Os filtros de datas são inclusivos e se referem ao encontro. O relatório recusa arquivos acima de 2 MB, mais de 20.000 itens, IDs duplicados, datas inválidas e históricos inconsistentes.

## Testes

    php -l main.php
    php -l reports.php
    php -l src/CatalogReports.php
    php -l tests/ReportsTest.php
    php main.php --self-test
    php tests/ReportsTest.php

O GitHub Actions executa lint, autoteste e as suítes de relatórios, operações e recibos em PHP 8.2, 8.3 e 8.4.

## Privacidade e limites

Não registrar nomes, telefones, fotos pessoais, detalhes identificadores ou descrições que facilitem reivindicação falsa. Verificar propriedade presencialmente antes da devolução. Os exemplos são fictícios. Manter JSON e CSV locais e privados, fora do Git.

Esta versão não possui autenticação, servidor público, Laravel, SQLite nem garantia de concorrência entre múltiplos processos. Use uma instância de escrita por vez. O relatório não publica informações na rede.

## Diagnósticos operacionais

O comando operations.php é somente leitura e usa o mesmo arquivo JSON:

    php operations.php diagnostico dados.json --referencia=2026-11-08
    php operations.php pendencias dados.json --referencia=2026-11-08 --dias=30
    php operations.php faixas dados.json --referencia=2026-11-08
    php operations.php fluxo dados.json --referencia=2026-11-08
    php operations.php duplicidades dados.json --janela=7

- Diagnóstico: backlog em uma data, faixas de idade, fluxo mensal e potenciais duplicidades.
- Pendências: itens ainda não devolvidos na data escolhida, com idade em dias.
- Fluxo: encontros e devoluções por mês, sem antecipar devoluções futuras.
- Duplicidades: mesma categoria e descrição normalizada, dentro da janela de dias. São apenas sugestões para revisão humana, não exclusões automáticas.
- Limites: até 500 pares sugeridos por consulta; nenhum dado é alterado.

Testes adicionais: php tests/OperationsTest.php e php tests/OperationsCliTest.php.

### Recibo interno anônimo

A classe `src/ReturnReceipt.php` gera um resumo determinístico de uma **devolução concluída** sem solicitar nome, telefone ou assinatura. Ela recusa itens pendentes, datas incoerentes e históricos sem evento de devolução. Trata-se de um registro interno, **não de um comprovante com validade jurídica ou assinatura**.

```bash
php src/ReturnReceipt.php catalogo.json ID-DO-OBJETO
php tests/return_receipt_test.php
```

O código usa somente PHP padrão e lê arquivos locais. O resumo não altera o catálogo.

## Planejamento técnico

O [roteiro original do primeiro incremento](docs/PRIMEIRO_INCREMENTO.md) foi preservado para histórico e próximos ciclos. Este MVP usa PHP e JSON local; Laravel e SQLite permanecem como possibilidades futuras, não dependências implementadas.
