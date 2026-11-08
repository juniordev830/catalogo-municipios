# Catálogo de Municípios

Ferramenta offline em PHP para consultar registros municipais com filtros, paginação, estatísticas, raio geográfico e exportação CSV/GeoJSON. Não consulta APIs, não modifica dados de origem e **não apresenta o conjunto de exemplo como base oficial do IBGE**.

## Requisitos

PHP 8.3 ou superior, sem Composer e sem dependências externas.

## Compatibilidade

A CLI original permanece inalterada:

```sh
php main.php examples/catalogo.json RN Natal
php main.php --self-test
```

A nova CLI aceita filtros opcionais:

```sh
php catalog.php examples/catalogo.json --uf=RN --termo=natal
php catalog.php examples/catalogo.json --sort=nome --order=desc --limit=10 --offset=0
php catalog.php examples/catalogo.json --min-pop=100000 --max-pop=1000000
php catalog.php examples/catalogo.json --latitude=-5.79 --longitude=-35.21 --raio-km=30 --sort=distancia
php catalog.php examples/catalogo.json --format=stats
php catalog.php examples/catalogo.json --format=csv
php catalog.php examples/catalogo.json --format=geojson
```

O resultado vai para stdout. Redirecione para um arquivo somente se desejar salvar: `php catalog.php examples/catalogo.json --format=csv > municipios.csv`.

## Estrutura do JSON

O arquivo deve conter `fonte` (identificação textual), `atualizado_em` (data real em AAAA-MM-DD) e `municipios` (lista). Cada município precisa de `id` (inteiro positivo único), `nome` (texto) e `uf` (uma das 27 siglas brasileiras).

São opcionais: `populacao` (inteiro não negativo), `latitude` e `longitude` (sempre juntas), e `codigo_ibge` (string de sete dígitos). Valores ausentes permanecem `null`; o aplicativo não inventa dados. Dados reais devem trazer a fonte, a data de referência e condições de uso.

## Funcionalidades

- Busca por nome sem diferenciar maiúsculas e acentos portugueses.
- Filtros por UF, população mínima e máxima.
- Ordenação por nome, UF, ID, população ou distância, com paginação estável.
- Busca em raio por fórmula de Haversine (distância aproximada em quilômetros), somente para registros com coordenadas.
- Estatísticas sobre cobertura de população, coordenadas e quantidades por UF.
- Exportação CSV com neutralização de células que poderiam ser interpretadas como fórmulas.
- Exportação GeoJSON com pontos na ordem correta longitude/latitude, omitindo registros sem coordenadas.

A API PHP expõe `Municipios\Catalog::validate()`, `search()`, `statistics()`, `csv()` e `geojson()`.

## Testes e CI

```sh
php -l main.php
php -l catalog.php
php -l src/Catalog.php
php -l tests/test_catalog.php
php main.php --self-test
php tests/test_catalog.php
```

O GitHub Actions executa análise de sintaxe, os testes originais e 23 novos cenários. A suíte cobre validações, filtros, ordenação, distâncias, exportação, neutralização de fórmulas e a CLI.

## Limitações

Não é uma base geográfica oficial, não faz geocodificação, não fornece fronteiras municipais e não implementa consulta por polígono. O cálculo de distância é aproximado e não representa distância rodoviária. O limite por consulta é 1000 registros, com no máximo 100000 registros carregados no catálogo. Não há dependência de serviços externos.
