# Achados e Perdidos

MVP em **PHP 8.3** para registrar objetos encontrados em ambientes comunitarios sem incluir dados pessoais de proprietarios.

## Funcionalidades

- Cadastra objetos por identificador, descricao generica, categoria e data.
- Lista itens pendentes por categoria e ordena por data de encontro.
- Registra devolucao e retira o item da lista pendente sem apagar seu historico.
- Rejeita IDs duplicados, datas invalidas e devolucoes repetidas ou anteriores ao encontro.
- Salva localmente em JSON por substituicao atomica no mesmo diretorio.

## Como executar

```bash
php main.php adicionar dados.json chaves-1 "Chaveiro simples" objetos 2026-11-08
php main.php adicionar dados.json roupas-1 "Blusa azul" roupas 2026-11-08
php main.php pendentes dados.json
php main.php pendentes dados.json roupas
php main.php devolver dados.json chaves-1 2026-11-08
php main.php todos dados.json
php main.php --self-test
```

## Privacidade e limites

Nao registrar nomes, telefones, fotos pessoais, detalhes identificadores ou descricoes que facilitem uma reivindicacao falsa. Exemplo e apenas ficticio; verificar propriedade antes da devolucao em um processo presencial.

Arquivo JSON local, uma instancia por vez. Nao ha login, servidor publico, Laravel, SQLite ou garantia de concorrencia entre multiplos processos nesta versao. Nao publicar o arquivo de dados no repositório.

Testes: PHP lint e cinco verificacoes de dominio no GitHub Actions.
