# Projeto inicial — Achados e Perdidos

**Estado confirmado:** apenas `README.md`. Ainda não há Laravel, banco de dados ou aplicação executável.

## Regras para o primeiro domínio

- `Objeto`: ID interno, categoria, descrição genérica, data de cadastro e situação.
- Situações previstas: `disponivel`, `reservado_para_verificacao` e `devolvido`.
- A devolução mantém registro interno, mas remove o objeto da listagem pública pendente.
- Não exibir identificação do proprietário nem detalhes de verificação que permitam falsos pedidos de retirada.

## Casos obrigatórios

1. Registrar item fictício, listar como pendente e marcá-lo devolvido.
2. O item devolvido não volta à busca de pendentes.
3. Registro de devolução conserva data e identificador interno.
4. Rejeitar categoria vazia, transição repetida e ID inexistente.
5. Testar descrições que contenham caracteres de HTML ou fórmulas antes de exportar.

## Segurança e implementação

Antes de interface pública, definir fluxo presencial de comprovação da propriedade e política de descarte de dados. PHP/Laravel/SQLite são apenas tecnologia proposta. Priorizar núcleo e testes isolados com objetos fictícios.
