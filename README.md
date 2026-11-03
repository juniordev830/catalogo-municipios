# Catálogo de Municípios

CLI em **PHP 8.3** para consultar um catálogo **local** de municípios por UF e parte do nome. Não exige extensões `intl` ou `mbstring`.

## Funcionalidades

- Busca insensível a maiúsculas/minúsculas e aos acentos portugueses mais comuns: `Sao`, `São` e `SÃO` podem localizar `São Gonçalo`.
- Filtra por UF de duas letras e valida IDs únicos e todos os registros, mesmo quando a pesquisa tem paginação.
- Ordena pelo nome normalizado e ID para resultados estáveis.
- `--pagina LIMITE DESLOCAMENTO`: retorna `total`, `limite`, `deslocamento` e até 100 itens por página; o índice inicial é zero. Sem a opção, preserva a estrutura anterior.
- Conserva metadados de fonte e data de atualização do próprio arquivo, sem inventar origem ou atualização automática.

## Uso

```sh
php main.php examples/catalogo.json RN Sao
php main.php examples/catalogo.json RN 'Gonçalo'
php main.php examples/catalogo.json RN Sao --pagina 20 0
php main.php --self-test
```

O exemplo de catálogo usa IDs **sintéticos** e não é uma extração oficial do IBGE.

## Testes

```sh
php -l main.php
php main.php --self-test
```

O GitHub Actions testa validações, duplicidade de IDs, limites de paginação e buscas com e sem acentos.

## Limitações

A normalização cobre os diacríticos portugueses mais frequentes, mas não constitui uma implementação Unicode completa. O arquivo JSON é limitado a 2 MB no modo CLI. Não acessa API do IBGE nem sincroniza/cadastra novas cidades. A data de atualização é a declarada no arquivo carregado, não uma garantia de atualidade da fonte.

Licença ainda não definida.
