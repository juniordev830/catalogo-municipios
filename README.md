# Catálogo de Municípios

Consulta de municípios e hierarquias territoriais com busca e cache.

## Implementado na primeira versão

**PHP — núcleo executável de domínio.** Filtra um catálogo local por UF e parte do nome, valida IDs únicos e inclui a origem e a data do arquivo na resposta.

## Como executar

Instale PHP 8.3 CLI. Não há dependências Composer. Os comandos abaixo partem da raiz do repositório.

```sh
php main.php examples/catalogo.json RN Natal
```

## Testes

```sh
php main.php --self-test
```

Os testes cobrem invariantes e entradas inválidas. A preparação local não dispõe deste runtime; o workflow em `.github/workflows/ci.yml` executa a validação no GitHub Actions. O resultado de cada execução está na aba Actions, associado ao commit.

## Organização e limites

Código e exemplos: `main.php e examples/catalogo.json`. Os dados de exemplo são fictícios. As funções de domínio são separadas da leitura de argumentos no mesmo arquivo para manter a primeira versão pequena e revisável.

O exemplo usa IDs sintéticos e não representa uma base oficial. Não há consulta ao IBGE, SQLite ou cache conectado ainda. Na branch de melhoria, a busca ignora diferenças entre maiúsculas e minúsculas e aceita termos sem acentos (por exemplo, `sao goncalo` encontra `São Gonçalo`). A normalização utiliza `iconv`, disponível na instalação PHP usada no CI. Os testes automatizados incluem esses dois cenários.

A proposta completa de evolução está em `ARQUITETURA.md`. As tecnologias propostas nessa arquitetura não devem ser interpretadas como dependências já implementadas.

## Próximas entregas

- [ ] Consultar dados do IBGE — ampliar o núcleo atual até o fluxo completo.
- [ ] Buscar municípios por nome e UF — ampliar o núcleo atual até o fluxo completo.
- [ ] Manter cache com data de atualização — ampliar o núcleo atual até o fluxo completo.

Licença: ainda não definida.
