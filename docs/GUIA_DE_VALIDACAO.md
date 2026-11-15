# Guia técnico — Catálogo de Municípios

## Estado atual

CLI PHP com dataset local demonstrativo; os municípios ainda não vêm diretamente de consulta oficial ao IBGE.

## Referências de código

`main.php`, `ARQUITETURA.md` e eventuais arquivos de exemplo locais.

## Validar mudanças

Executar `php -l main.php`; consultar opções CLI e executar a verificação `php main.php --self-test` documentada no README.

Usar somente dados fictícios e registrar comandos executados com resultados no PR. A mera presença de um teste no repositório não comprova que ele passou.

## Cenários de regressão

Testar UF inexistente, IDs repetidos, diferença de maiúsculas, consulta vazia e ordenação estável de nomes.

## Próximo incremento

Próxima etapa: importar dados públicos com fonte, data de atualização, checagem de integridade e comportamento offline definidos.

## Critérios de revisão

- [ ] Código e documentação refletem o mesmo comportamento.
- [ ] Casos de fronteira estão protegidos por teste ou roteiro de validação.
- [ ] Arquivos e saídas geradas ficam fora do versionamento, quando contêm dados privados.
- [ ] Mudanças passam por branch e PR, sem integração automática.
