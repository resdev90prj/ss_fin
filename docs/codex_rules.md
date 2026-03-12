# Regras de Trabalho do Codex

## Principios operacionais
- Ler o contexto atual antes de alterar qualquer arquivo.
- Priorizar entendimento do fluxo existente antes de propor mudancas.
- Fazer alteracoes minimas e focadas no escopo solicitado.
- Evitar refatoracoes amplas sem solicitacao explicita.

## Reuso antes de criar
- Reutilizar `models`, `controllers`, `helpers` e padroes ja existentes.
- So criar novos arquivos quando a funcionalidade nao encaixar no que ja existe.
- Evitar criar novas camadas arquiteturais sem necessidade real.

## Arquitetura e padrao do projeto
- Respeitar o padrao MVC simples atual.
- Manter roteamento centralizado em `index.php` com `route`.
- Seguir convencoes de nomenclatura ja usadas no projeto.
- Preservar compatibilidade com execucao em PHP puro + PDO.

## Seguranca e consistencia
- Exigir autenticacao em rotas administrativas.
- Manter protecao CSRF em acoes mutaveis.
- Usar prepared statements via PDO em consultas com entrada do usuario.
- Escapar saidas de UI com helper `e()`.

## Banco de dados e schema
- Nao recriar schema completo quando a demanda for incremental.
- Para mudanca estrutural, preferir patch SQL especifico e objetivo.
- Nao assumir colunas/tabelas; validar no codigo e/ou no banco antes de depender delas.

## Escopo e impacto
- Nao modificar modulos fora do pedido sem justificativa tecnica clara.
- Se detectar problema critico fora do escopo, reportar e pedir direcao.
- Evitar mudancas destrutivas de dados.

## Compatibilidade de ambiente
- Preservar execucao sem Composer obrigatorio.
- Evitar dependencias externas obrigatorias.
- Manter solucoes compativeis com hospedagem compartilhada.

## Documentacao interna
- Registrar decisoes tecnicas relevantes no contexto do projeto.
- Atualizar documentacao quando houver mudanca estrutural, regra nova ou integracao nova.

## Atualizacao obrigatoria do contexto
- Sempre que uma nova feature relevante for implementada ou quando houver mudanca estrutural no sistema, o arquivo `docs/codex_context.md` deve ser atualizado.
- Essas atualizacoes devem registrar:
  - novos modulos criados;
  - mudancas de arquitetura;
  - novas integracoes;
  - novas regras de negocio;
  - alteracoes relevantes na estrutura de pastas;
  - decisoes tecnicas importantes.
- Objetivo: manter `docs/codex_context.md` como memoria viva do projeto.
