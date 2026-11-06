# Achados e Perdidos — Laravel API MVP

API local em **PHP 8.2+ / Laravel 11 / SQLite** para cadastrar objetos encontrados sem registrar dados pessoais.

## Implementado
- Registrar categoria, descrição pública genérica e data.
- Consultar objetos pendentes filtrando categoria e período.
- Registrar devolução com protocolo interno e data.
- Objeto devolvido desaparece da listagem pendente, mas permanece consultável no histórico pelo ID.
- Validação de entradas e teste de integração.

## Iniciar
Requer PHP 8.2+, Composer e extensão SQLite:
```bash
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1
```
No Windows, crie manualmente o arquivo vazio `database/database.sqlite` antes de migrar.

## Rotas
- `GET /api/items?category=livros&from=2026-11-01&to=2026-11-30`
- `POST /api/items` — `{"category":"livros","public_description":"Caderno simples","found_at":"2026-11-05"}`
- `POST /api/items/{id}/return` — `{"return_reference":"protocolo-001"}`
- `GET /api/items/{id}` — histórico do item

## Testar
```bash
php artisan test
```

## Segurança e limites
**Apenas ambiente local ou demonstrativo.** Ainda não há autenticação, papéis administrativos, proteção contra abuso ou interface gráfica. Não exponha esta API publicamente antes de implementar autorização para cadastro, consulta detalhada e devoluções. Nunca inclua detalhes privados que permitam falsa reivindicação do objeto.
