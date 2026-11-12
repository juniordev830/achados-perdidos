# Achados e Perdidos

> Estado nesta branch: núcleo de domínio PHP executável com testes e demonstração. A aplicação web/Laravel e o banco de dados continuam planejados.

## Ideia

Organizar objetos encontrados em escolas, igrejas e espaços comunitários.

## Primeira versão planejada

- [ ] Registrar objeto encontrado sem dados pessoais.
- [ ] Consultar por categoria e período.
- [ ] Marcar devolução com registro interno.

## Modelo inicial

Objeto, categoria e devolução.

## Regras e limites

Omitir detalhes que permitam falsa identificação; devolução encerra o anúncio.

Escopo mantido pequeno: sem pagamentos, integrações externas, infraestrutura de produção ou uso de dados pessoais reais nesta etapa.

## Tecnologia proposta

PHP + Laravel + SQLite.

PHP já é utilizado no núcleo em memória. Laravel e SQLite permanecem planejados; não há aplicação web nem dependências externas instaladas.

## Critério de conclusão do primeiro incremento

Objeto devolvido deve sair da lista de pendentes e manter seu histórico.

## Próximos passos

1. Revisar este escopo antes de começar a implementação.
2. Preparar o ambiente mínimo da tecnologia escolhida.
3. Implementar um fluxo completo e validar o critério acima.
4. Documentar a execução e acrescentar exemplos demonstrativos.

## Possíveis evoluções

Depois de validar o fluxo principal, avaliar persistência, acessibilidade e uma interface mais completa conforme a plataforma. Evoluções não fazem parte da entrega atual.

## Como executar

Ainda não há comandos de execução. Eles serão adicionados quando existir uma primeira versão funcional.

## Documentação técnica

Confira o [roteiro de validação e próximos incrementos](docs/PRIMEIRO_INCREMENTO.md) para revisar a implementação e os critérios de qualidade antes de mudanças futuras.

## Núcleo PHP implementado nesta branch

O catálogo em `src/Catalogo.php` registra objetos fictícios sem identificação de proprietários, permite filtrar pendências por categoria e marcar devoluções preservando o registro interno. As operações validam IDs, tamanho da descrição e datas ISO.

Requer PHP 8.3+ com a extensão `mbstring`:

```bash
php bin/exemplo.php
php tests/CatalogoTest.php
```

A CI da branch executa esses comandos pelo workflow `.github/workflows/php-domain.yml`.

**Limites:** os dados ficam apenas em memória durante a execução. Não há login, autenticação de quem solicita a devolução, persistência, comunicação automática, Laravel ou interface pública. Um processo real precisaria verificar a propriedade do item e aplicar proteção de dados antes de disponibilizar atendimento.
