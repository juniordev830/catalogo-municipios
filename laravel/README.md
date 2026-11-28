# Catálogo de municípios — API Laravel 13

Consulta e estatísticas do catálogo de exemplo sintético já versionado. Não usa dados oficiais do IBGE.

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
GET /api/municipios?uf=RN&limit=10
GET /api/municipios/estatisticas
```

GET /up verifica o bootstrap. Respostas JSON; entradas inválidas retornam 422. O teste executa requisições diretamente pelo kernel HTTP. O lock do Composer precisa ser gerado e revisado com as dependências instaladas antes de publicar uma versão reproduzível.
