# Engenharia reversa e estudo — Catálogo de Municípios

## Objetivo e fluxo

Busca e exportação offline de registros municipais. src/Catalog.php unifica validação, consultas e formatos de saída. catalog.php expõe essas operações, e main.php mantém a consulta mais simples.

**Tecnologias identificadas** · PHP.

**Revisão de referência** · `d3d2df1b901c`.

## Mapa do código

| Caminho | Papel de leitura | Símbolos selecionados |
| --- | --- | --- |
| [catalog.php](../catalog.php) | Implementação | `catalogCli` |
| [main.php](../main.php) | Ponto de leitura para entrada ou composição | `normalizeSearch`, `searchCities` |
| [src/Catalog.php](../src/Catalog.php) | Implementação | `Municipios`, `Catalog`, `validate`, `search`, `statistics`, `csv`, `geojson`, `safeCsv`, `origin` |

## Configuração e referências

Referências existentes: [ARQUITETURA.md](../ARQUITETURA.md), [docs/GUIA_DE_VALIDACAO.md](GUIA_DE_VALIDACAO.md).

## Roteiro de estudo e desenvolvimento

1. Comece pelo fluxo descrito acima e localize seus pontos de entrada no mapa. Acompanhe uma entrada válida até a transformação ou a resposta.
2. Abra um teste relacionado ao fluxo e descreva qual regra ele protege. Compare o tratamento esperado para entradas válidas, inválidas e nos limites.
3. Ao adicionar uma regra, determine qual módulo controla o dado e cubra o caso nesse nível. Em seguida, confira o efeito no comando, na API ou na interface de entrada.

**Pergunta de entrevista**

Como testaria uma consulta por raio próximo do limite do filtro?

## Testes versionados

Arquivos para começar a leitura dos cenários de teste: [tests/test_catalog.php](../tests/test_catalog.php).

Os comandos e versões de runtime usados no CI ficam em [.github/workflows/ci.yml](../.github/workflows/ci.yml).

[Voltar ao README](../README.md).
