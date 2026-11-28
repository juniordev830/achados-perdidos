# achados-perdidos — API Laravel 13

Consulta paginada e resumo de devoluções sem exposição de campos privados.

## Instalar e executar

Requer PHP 8.3 ou 8.4, extensões do Laravel e Composer 2. A dependência é `laravel/framework:^13.0`; o `composer.lock` registra a resolução revisada. A aplicação PHP original continua independente.

```sh
cd laravel
composer install --no-interaction --prefer-dist
composer validate --no-check-publish
composer audit
php artisan route:list --json
composer test
php artisan serve --host=127.0.0.1 --port=8000
```

Copie `.env.example` para `.env` somente em instalação nova. Não versione esse arquivo nem segredos. `CACHE_STORE=file` é o padrão para manter o contador de requisições entre chamadas. O diretório `storage/framework/cache/data` precisa ser gravável. `CACHE_STORE=array` é reservado aos testes em processo único; ele não limita chamadas entre processos web.

## Contrato HTTP

| Método | Rota | Uso |
|---|---|---|
| POST | `/api/relatorios/consulta` | `{"catalogo":{"itens":[]},"filtros":{"status":"pendente","pagina":1,"limite":25}}` |
| POST | `/api/relatorios/resumo` | `{"catalogo":{"itens":[]},"data_referencia":"2026-11-28"}` |

`ReportController` reutiliza `CatalogReports`. A consulta permite apenas `id`, `objeto`, `categoria`, `encontrado_em`, `status` e `devolvido_em` na saída. Protocolos, contatos, documentos e histórico interno não são serializados. O resumo retorna agregados. Raiz, catálogo e filtros devem ser objetos JSON; limite de 2 MB. A API não grava, cadastra nem devolve objetos: essas operações continuam no fluxo original.

Exemplo: `curl -H 'Content-Type: application/json' --data '{"catalogo":{"itens":[]},"data_referencia":"2026-11-28"}' http://127.0.0.1:8000/api/relatorios/resumo`.


## Arquitetura e limites

`bootstrap/app.php` registra rotas, respostas JSON de erro e limite de 60 chamadas por minuto por IP. Respostas 429 incluem `Retry-After`. `config/filesystems.php` desativa rotas de upload/download desnecessárias. As rotas desta API não usam Eloquent, sessões, filas, banco ou armazenamento de uploads, pois essas funções não são necessárias ao escopo. As classes do domínio são reutilizadas por autoload do Composer, sem copiar regras.

Entradas inválidas retornam 422, formato não suportado 415 e corpos acima do limite 413 nos endpoints JSON. Erros inesperados não devem expor stack traces: mantenha `APP_DEBUG=false`. Não há CORS personalizado para integrar uma SPA pública. Use o servidor em loopback; publicação exige uma decisão separada sobre identidade de usuários, autorização, TLS, proxy confiável e operação.

## Verificação

`composer test` executa smoke tests e regressões diretamente pelo kernel HTTP. As regressões cobrem casos válidos e inválidos, proteção de saída, limites, 429 e ausência das rotas de armazenamento. Exceções do executor encerram o processo com código 1. O workflow `Laravel API` instala dependências pelo lock, executa auditoria, lista rotas e roda os testes em PHP 8.3 e 8.4. A suíte original da raiz continua no workflow existente.

O bootstrap mínimo usa um executor HTTP independente; `composer test` é o comando de teste deste módulo. Não há PHPUnit nem comando `artisan test` instalado aqui.

O Composer resolve as dependências com `config.platform.php=8.3.0` para manter o lock instalável em PHP 8.3 e 8.4. Não use `--ignore-platform-reqs` para contornar incompatibilidades.
