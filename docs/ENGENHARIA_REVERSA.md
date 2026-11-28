# Engenharia reversa e estudo — Achados e Perdidos

## Objetivo e fluxo

Catálogo local e relatórios de itens encontrados. main.php reúne o cadastro legado, CatalogReports valida e consulta os dados e Operations calcula filas, envelhecimento e possíveis duplicidades.

**Tecnologias identificadas** · PHP.

**Revisão de referência** · `38a2e84a633f`.

## Mapa do código

| Caminho | Papel de leitura | Símbolos selecionados |
| --- | --- | --- |
| [main.php](../main.php) | Ponto de leitura para entrada ou composição | `dateIsValid`, `items`, `addFound`, `markReturned`, `pending`, `readData`, `saveData`, `updateDataFile`, `selfTest` |
| [operations.php](../operations.php) | Implementação | `operationsUsage`, `operationsOptions`, `operationsResult` |
| [reports.php](../reports.php) | Implementação | `reportUsage`, `parseReportOptions`, `reportOutput` |
| [src/CatalogReports.php](../src/CatalogReports.php) | Implementação | `CatalogReports`, `read`, `validate`, `validDate`, `search`, `summary`, `timeline`, `toCsv`, `safeCell` |
| [src/Operations.php](../src/Operations.php) | Implementação | `CatalogOperations`, `backlogAsOf`, `staleItems`, `agingBuckets`, `monthlyFlow`, `possibleDuplicates`, `inventoryHealth`, `normalize`, `requireDate` |
| [src/ReturnReceipt.php](../src/ReturnReceipt.php) | Implementação | `ReturnReceipt`, `validDate`, `build` |

## Roteiro de estudo e desenvolvimento

1. Comece pelo fluxo descrito acima e localize seus pontos de entrada no mapa. Acompanhe uma entrada válida até a transformação ou a resposta.
2. Abra um teste relacionado ao fluxo e descreva qual regra ele protege. Compare o tratamento esperado para entradas válidas, inválidas e nos limites.
3. Ao adicionar uma regra, determine qual módulo controla o dado e cubra o caso nesse nível. Em seguida, confira o efeito no comando, na API ou na interface de entrada.

**Pergunta de entrevista**

Como identificar um possível duplicado sem apagar o registro original?

## Testes versionados

Arquivos para começar a leitura dos cenários de teste: [tests/ConcurrentWritesTest.php](../tests/ConcurrentWritesTest.php), [tests/OperationsCliTest.php](../tests/OperationsCliTest.php), [tests/OperationsTest.php](../tests/OperationsTest.php), [tests/ReportsTest.php](../tests/ReportsTest.php), [tests/return_receipt_test.php](../tests/return_receipt_test.php).

Os comandos e versões de runtime usados no CI ficam em [.github/workflows/ci.yml](../.github/workflows/ci.yml).

[Voltar ao README](../README.md).
