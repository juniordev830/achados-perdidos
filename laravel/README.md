# Achados e Perdidos — API Laravel 13

Consulta de relatórios com catálogo JSON fornecido no corpo da requisição, sem acesso a arquivos privados nem gravação. Utilize somente dados fictícios.

Aplicação complementar e independente: os comandos PHP da raiz permanecem inalterados. Reaproveita as classes do domínio sem duplicar regras. Requer PHP 8.3+ e Composer 2.

## Instalação local

```sh
cd laravel
composer install
php artisan route:list
composer test
php artisan serve --host=127.0.0.1 --port=8000
```

Opcionalmente copie .env.example para .env (não versionar). A API não usa sessões, banco, autenticação nem persistência. Não exponha a API à internet; use somente loopback e dados fictícios. Antes de publicar implemente autorização, limites de requisição no servidor, monitoramento e revisão de segurança.

## Endpoints

```
POST /api/relatorios/consulta

curl -H 'Content-Type: application/json' -d '{"catalogo":{"itens":[]},"filtros":{"status":"pendente"}}' http://127.0.0.1:8000/api/relatorios/consulta
```

GET /up verifica o bootstrap. Respostas JSON; entradas inválidas retornam 422. O teste executa requisições diretamente pelo kernel HTTP. O lock do Composer precisa ser gerado e revisado com as dependências instaladas antes de publicar uma versão reproduzível.
