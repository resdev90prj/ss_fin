# Codex Context - PROJETO_SAAS_IA_FINAN

## Visao geral do projeto
- Sistema financeiro web em PHP para gestao de contas, transacoes, dividas, metas, orcamentos e importacoes.
- Implementacao atual em MVC simples, sem framework full-stack.
- Suporte multiusuario com isolamento de dados por login e perfil (`admin` e `user`).
- Modulo de planejamento estruturado implementado: `Alvos, Objetivos e Execucao`.
- Classificacao automatica de categorias implementada em transacoes e importacoes com base no historico do proprio usuario (sem API externa).
- Dashboard evoluido com `Central de Execucao` para destacar alertas de prazo e prioridades de acoes do alvo ativo.
- Agenda de Execucao Diaria implementada com bloco resumido no dashboard (`Agenda de Hoje`) e tela expandida (`Agenda de Execucao`).
- Score de Execucao Semanal implementado no dashboard com comparacao semanal e historico de evolucao.
- Central de Alertas implementada com envio de digest por e-mail e arquitetura desacoplada pronta para futuro provider de WhatsApp.
- Contexto atualizado em: 2026-03-12.

## Objetivo do sistema
- Centralizar fluxo financeiro PF/PJ com visao operacional e analitica.
- Registrar receitas/despesas/retiradas.
- Controlar dividas e parcelas.
- Importar extratos (manual e fila OFX automatizada).

## Stack tecnologica
- Backend: PHP puro.
- Banco: MySQL/MariaDB via PDO.
- Frontend: PHP views + Tailwind CSS via CDN + Chart.js via CDN.
- Servidor local observado: XAMPP (`C:\xampp`), `php.exe` em `C:\xampp\php\php.exe`.
- Composer: nao utilizado no projeto.

## Arquitetura
- Padrao: MVC simples em um monolito.
- Entrada unica: `index.php` com roteamento por query string (`route`).
- `public_html/index.php` atua como bootstrap para carregar `../index.php` quando o docroot da hospedagem aponta para `public_html`.
- Controllers orquestram fluxo e validacoes.
- Models encapsulam SQL/CRUD via PDO.
- Views renderizadas por funcao `view()` com `header/sidebar/footer`.
- Sessao + autenticacao propria (`includes/helpers.php`, `includes/auth.php`).

## Estrutura de diretorios
- `controllers/`: controllers por modulo.
- `models/`: acesso a dados e regras de dominio.
- `views/`: telas por modulo + layouts.
- `includes/`: config, DB, auth, helpers e automacao OFX.
- `includes/notifications/`: providers de notificacao (email atual + stub WhatsApp futuro).
- `database/patches/`: patches SQL incrementais de schema.
- `database/schema.sql`: schema base.
- `imports/`: fila OFX (`pending`, `processed`, `error`, `logs`).
- `public_html/`: front publico e uploads.
- `public_html/assets/branding/`: ativos de marca (favicon/logo geral).
- `favicon.ico` (raiz) e `public_html/favicon.ico`: fallback de favicon para evitar icone padrao do ambiente.
- `deploy/`: rotinas e artefatos de publicacao para hospedagem compartilhada (Hostinger).

## Modulos principais
- Autenticacao: login/logout por sessao.
- Usuarios (`users`): gestao administrativa de usuarios (listar, criar, editar, ativar/desativar, reset/alteracao de senha com confirmacao, troca de escopo de visualizacao) e area de autoatendimento `Meu acesso` para atualizar nome/e-mail e trocar a propria senha com validacao da senha atual.
- Dashboard: KPIs por competencia, evolucao e projecoes com parcelas + `Central de Execucao` (sino de notificacoes, atencao imediata, proximas acoes, painel lateral de execucao e indicadores operacionais) + bloco `Agenda de Hoje`.
- Agenda (`agenda_execution`): tela expandida da execucao diaria com ordenacao automatica das acoes abertas do usuario.
- Score semanal: bloco `Score de Execucao Semanal` no dashboard com nota `0-100`, classificacao interpretativa, comparacao com semana anterior e historico visual das ultimas semanas.
- Central de Alertas (`alerts`):
  - gera alertas operacionais por usuario com base em acoes pendentes/em andamento;
  - envia e-mail por provider `php mail()` respeitando preferencia individual;
  - prepara camada de providers para canal futuro de WhatsApp sem acoplamento ao dominio;
  - suporta execucao manual (rota admin) e automatizada por script CLI/cron.
- Alvos e Execucao (`targets`):
  - hierarquia `alvo -> objetivos -> decisoes -> acoes`;
  - CRUD completo por nivel com segregacao por usuario;
  - progresso baseado em acoes realizadas;
  - integracao com dashboard (alvo ativo, objetivo atual, proximas acoes e alerta de atraso).
- Contas (`accounts`): cadastro, listagem, status.
- Caixas (`boxes`): cadastro e vinculo opcional com conta.
- Categorias (`categories`): CRUD com categoria padrao protegida.
- Transacoes (`transactions`): CRUD + filtros + deduplicacao auxiliar para OFX + sugestao automatica de categoria por historico (`high|medium|low`) + acao em lote para reclassificar lancamentos em `Outros gastos`.
- Retiradas (`withdrawals`): lancamentos do tipo `partner_withdrawal`.
- Dividas (`debts`): cadastro parcelado, pagamento, estorno parcial/total por parcela, exclusoes condicionadas, juros/multa com composicao mensal.
- Orcamentos (`budgets`): limite mensal por categoria.
- Metas (`goals`): objetivos financeiros.
- Relatorios (`reports`): listagem filtrada com resultado liquido.
- Importacoes (`imports`):
  - upload manual CSV/OFX/XLSX;
  - fila OFX automatizada por rota protegida e script CLI;
  - classificacao automatica por historico do usuario com fallback de categoria por tipo (despesa nao reconhecida prioriza `Outros gastos`).
- Deploy/Migracao (`deploy`):
  - geracao de pacote `deploy_hostinger.zip` com exclusoes de ambiente local;
  - exportacao de banco para `backup_banco.sql` via `mysqldump`;
  - script orquestrador para gerar artefatos em lote;
  - guia objetivo de migracao em `deploy/README_HOSTINGER.md`.

## Modulos parciais
- Gestao de usuarios/perfis alem login basico: completo no escopo admin/user.
- Edicao em alguns modulos via UI: parcial (ha casos com acao existente e UX limitada).
- Testes automatizados: inexistente no repositorio.

## Modulos pendentes
- API externa/documentada: a validar.
- Job scheduler/cron em producao para fila OFX: a validar.
- Auditoria estruturada de eventos (alem de logs em arquivo): a validar.

## Regras de negocio relevantes
- Rotas publicas restritas (`login`, `login_submit`); demais exigem autenticacao.
- Sessao valida usuario ativo a cada requisicao protegida; usuarios inativos perdem acesso imediatamente.
- Permissoes de acesso:
  - apenas admin pode listar todos os usuarios, criar novo acesso, editar outros usuarios e ativar/desativar usuarios;
  - usuario autenticado pode atualizar apenas os proprios dados basicos (`Meu acesso`) e trocar a propria senha mediante confirmacao da senha atual;
  - desativacao do proprio usuario logado via tela administrativa permanece bloqueada para evitar perda de acesso.
- Isolamento por login:
  - usuario comum acessa apenas os dados vinculados ao proprio `user_id`;
  - admin pode operar em escopo proprio ou em escopo de outro usuario (contexto de visualizacao), sem trocar identidade de login.
- Acoes de escrita em modulos financeiros validam propriedade de referencias (`account_id`, `category_id`, `box_id`) para evitar IDOR indireto.
- Acoes mutaveis exigem CSRF na maior parte dos formularios.
- Categorias:
  - estrategia adotada: template de categorias padrao do sistema replicado por usuario (`ensureDefaultsForUser`);
  - usuario pode criar categorias personalizadas alem das padroes.
- Classificacao automatica de categoria:
  - base de aprendizado local por usuario em `category_classifier_memory`;
  - descricao normalizada (lowercase + transliteracao + remocao de simbolos) usada para comparacao;
  - camadas de decisao: correspondencia exata, similaridade parcial, palavras-chave frequentes e frequencia historica;
  - niveis de confianca: `high`, `medium`, `low`;
  - cadastro manual: `high` pode preencher automaticamente; `medium` exibe sugestao para confirmacao;
  - importacao manual/OFX: `high` e `medium` classificam automaticamente; `low` usa fallback coerente por tipo;
  - tela de transacoes possui botao de classificacao em lote para tentar reclassificar todos os registros em `Outros gastos`;
  - no reprocessamento em lote de `Outros gastos`, o algoritmo evita manter `Outros gastos` como destino e tenta alternativa por historico nao-`Outros gastos` (exata e parcial) antes de manter pendente;
  - quando a memoria de classificacao estiver vazia para o usuario, o sistema faz bootstrap automatico a partir de transacoes antigas ja classificadas (exceto `Outros gastos`) antes de reprocessar em lote;
  - apos processamento em lote, o grid pode priorizar os registros ainda em `Outros gastos` e exibe contador de pendencias no filtro atual;
  - o retorno da classificacao em lote inclui diagnostico de nao reclassificados (sugestao voltando para `Outros gastos`, baixa confianca, ausencia de historico alternativo, tokens insuficientes, sinal historico fraco e falha de update);
  - todo aprendizado permanece segregado por `user_id`.
- Planejamento (`targets`):
  - um usuario pode ter varios alvos, mas somente **um alvo ativo por vez**;
  - dentro de um alvo, somente **um objetivo ativo por vez**;
  - cada objetivo permite no maximo **3 decisoes**;
  - progresso (decisao/objetivo/alvo) = `acoes realizadas / total de acoes validas` (acoes `cancelled` nao contam);
  - sem acoes cadastradas, progresso = `0%`;
  - dashboard mostra o alvo ativo, objetivo atual, prioridade automatica de acoes (`critico`, `alta`, `media`, `baixa`, `sem prazo`) e alerta de objetivo atrasado;
  - notificacoes internas da Central de Execucao consideram somente acoes pendentes/em andamento (`status in pending,in_progress` e `is_done = 0`);
  - badge do sino contabiliza acoes atrasadas, vencendo hoje e vencendo em ate 3 dias;
  - painel lateral `Minhas acoes de hoje` prioriza atrasadas, vencendo hoje e acoes importantes do objetivo ativo.
  - Agenda de Execucao Diaria considera apenas acoes `pending` e `in_progress` (com `is_done = 0`) e ordena por regra fixa:
    1) atrasadas,
    2) vencendo hoje,
    3) vencendo em ate 3 dias,
    4) vinculadas ao objetivo ativo,
    5) vinculadas ao alvo ativo,
    6) demais pendentes.
  - Score de Execucao Semanal:
    - usa recorte semanal (segunda a domingo) por usuario autenticado;
    - pontos positivos por acoes concluidas na semana e taxa de conclusao vs previstas;
    - bonus para acoes concluidas vinculadas ao objetivo ativo e ao alvo ativo;
    - penalidade para acoes atrasadas ainda nao executadas;
    - comparacao automatica com semana anterior (`delta` e tendencia);
    - classificacao interpretativa: `Excelente`, `Bom`, `Atencao`, `Critico`.
- Central de Alertas:
  - gera alertas para `acoes atrasadas`, `vencendo hoje`, `vencendo em ate 3 dias`, `muitas pendencias`, `objetivo ativo sem avancos recentes` e `baixa execucao semanal`;
  - considera apenas acoes abertas (`pending`, `in_progress`, `is_done = 0`) para notificacoes de prazo;
  - respeita `user_id`, status ativo e preferencias por usuario (`receber_alerta_email`, `email_notificacao`, `alerta_frequencia`, `alerta_horario`);
  - nao envia alerta de prazo para acao concluida;
  - objetivo ativo sem avanco usa janela recente de 7 dias (acoes concluidas no objetivo ativo).
- OFX fila:
  - le `imports/pending`;
  - move para `processed`/`error`;
  - evita duplicidade por hash de arquivo e por transacao (FITID + combinacao data/valor/tipo/descricao normalizada);
  - logs em `imports/logs`;
  - registra estatisticas de classificacao automatica (alta, media, fallback).
- Importacao manual valida conta de destino no escopo do usuario autenticado.
- Dividas:
  - exclusao de divida/parcela permitida apenas quando pendente;
  - bloqueio de exclusao se houver qualquer parcela paga na divida;
  - pagamentos podem ser estornados (parcial ou total) por parcela, respeitando limite do valor ja pago;
  - apos estorno total de todas as parcelas pagas, a exclusao volta a ser permitida;
  - juros/multa com modo `%` ou `R$` (`fixed`), composicao mensal sobre saldo em aberto;
  - composicao depende das colunas de configuracao em `debts`.

## Convencoes tecnicas observadas
- Nome de rota por query (`index.php?route=...`).
- Controllers em `PascalCaseController.php`; models em `PascalCase.php`.
- SQL via prepared statements PDO.
- `PDO::ATTR_EMULATE_PREPARES = false` (placeholders devem ser tratados com cuidado).
- Escaping de saida com helper `e()`.
- Flash messages por sessao.
- Arquivos PHP ativos padronizados para UTF-8 sem BOM para evitar saida antes de `session_start()` e `header()` em hospedagem.
- Diagnostico de runtime opcional no bootstrap (`index.php`) controlado por `debug.enabled` em `includes/config.php`/`includes/config.custom.php`, com `display_errors` desligado por padrao.
- Dashboard com fallback de resiliencia por bloco (queries criticas encapsuladas com `try/catch` e `error_log`) para evitar HTTP 500 por divergencia pontual de schema/dados em producao.
- Consultas mensais de resumo/categorias no dashboard padronizadas para recorte por faixa de datas (`>= inicio_mes` e `< inicio_mes_seguinte`) para manter consistencia com a evolucao mensal e evitar divergencia por funcao SQL de formatacao.
- Central de Execucao consolidada em `Target::dashboardData()` com consultas limitadas (acoes abertas do alvo ativo + resumo complementar), para manter desempenho no carregamento do dashboard.
- Agenda de Execucao consolidada em `Target::executionAgendaData()` com consulta limitada de acoes abertas por usuario (prepared statements + `user_id`) e ordenacao em camada de dominio.
- Score semanal consolidado em `Target::executionWeeklyScoreData()` com historico calculado em tempo de execucao (sem tabela nova), mantendo compatibilidade com hospedagem compartilhada.
- Central de Alertas consolidada em `includes/AlertCenterService.php`, reaproveitando `Target::executionAgendaData()`, `Target::executionWeeklyScoreData()` e `Target::activeObjectiveProgressSnapshot()`.
- Providers de notificacao usam contrato unico (`NotificationProviderInterface`) para manter expansao de canais sem alterar regras de negocio.
- Logs de disparo persistem em `notification_dispatch_logs` (com fallback em arquivo quando tabela nao existir).
- Branding: favicon global e logo lateral reutilizam `public_html/assets/branding/finance_logo.ico`.
- Deploy: scripts locais em PHP/BAT geram artefatos de migracao sem Composer/Docker.

## Arquivos criticos
- Entrada e roteamento: [index.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/index.php)
- Config app/DB: [includes/config.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/config.php), [includes/db.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/db.php)
- Auth/helpers: [includes/auth.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/auth.php), [includes/helpers.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/helpers.php)
- Usuarios/perfis: [controllers/UserController.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/controllers/UserController.php), [models/User.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/User.php), [views/users/index.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/views/users/index.php)
- Alertas:
  - controller: [controllers/AlertController.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/controllers/AlertController.php)
  - service: [includes/AlertCenterService.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/AlertCenterService.php)
  - providers: [includes/notifications/EmailNotificationProvider.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/notifications/EmailNotificationProvider.php), [includes/notifications/WhatsAppNotificationProvider.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/notifications/WhatsAppNotificationProvider.php)
  - script CLI: [includes/process_alerts.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/process_alerts.php)
  - patch SQL: [database/patches/20260312_alert_center_notifications.sql](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/database/patches/20260312_alert_center_notifications.sql)
- Planejamento:
  - controller: [controllers/TargetController.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/controllers/TargetController.php)
  - models: [models/Target.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/Target.php), [models/Objective.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/Objective.php), [models/Decision.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/Decision.php), [models/PlanAction.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/PlanAction.php)
  - views: [views/targets/index.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/views/targets/index.php), [views/targets/show.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/views/targets/show.php)
  - patch SQL: [database/patches/20260308_targets_objectives_execution.sql](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/database/patches/20260308_targets_objectives_execution.sql)
- Classificacao automatica:
  - service: [includes/CategoryAutoClassifier.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/CategoryAutoClassifier.php)
  - patch SQL: [database/patches/20260308_category_classifier_memory.sql](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/database/patches/20260308_category_classifier_memory.sql)
  - integracao em transacoes/importacao: [controllers/TransactionController.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/controllers/TransactionController.php), [controllers/ImportController.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/controllers/ImportController.php), [includes/OfxQueueProcessor.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/OfxQueueProcessor.php), [models/Transaction.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/Transaction.php)
- Schema base: [database/schema.sql](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/database/schema.sql)
- OFX automacao: [includes/OfxParser.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/OfxParser.php), [includes/OfxQueueProcessor.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/OfxQueueProcessor.php), [includes/process_ofx_queue.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/includes/process_ofx_queue.php)
- Dividas: [controllers/DebtController.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/controllers/DebtController.php), [models/Debt.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/Debt.php), [models/DebtInstallment.php](/C:/xampp/htdocs/PROJETO_SAAS_IA_FINAN/models/DebtInstallment.php)

## Decisoes arquiteturais ja tomadas
- Manter aplicacao sem framework externo, privilegiando portabilidade em hospedagem compartilhada.
- Manter rotas centralizadas em `index.php` (switch-case).
- Manter segregacao de dados em todos os modulos via `user_id`, sem criar nova camada de autorizacao externa.
- Implementar contexto de visualizacao para admin no proprio `includes/auth.php` (escopo em sessao), evitando duplicar controllers/views para modo admin.
- Implementar modulo de planejamento como subdominio interno no mesmo MVC (sem camada paralela), com regras de execucao no controller/model e visualizacao hierarquica nas views.
- Reaproveitar dashboard existente para exibir resumo do planejamento ativo e evoluir para `Central de Execucao`, sem criar modulo separado.
- Reaproveitar o mesmo fluxo MVC para disponibilizar a Agenda de Execucao via rota dedicada (`index.php?route=agenda_execution`) e bloco resumido no dashboard, sem nova camada arquitetural.
- Reaproveitar o dashboard existente para expor o Score de Execucao Semanal e historico comparativo sem adicionar camada de persistencia extra.
- Reusar regras de transacao/categoria no processamento OFX em vez de criar camada paralela.
- Implementar classificacao "inteligente" por historico local (DB) sem IA externa, mantendo execucao em PHP/PDO compativel com hospedagem compartilhada.
- Executar automacoes com opcao web protegida e script CLI.
- Centralizar notificacoes em service desacoplado com interface de provider para habilitar email imediato e WhatsApp futuro sem refatoracao do dominio.
- Manter disparo de alertas compativel com hospedagem compartilhada via rota admin protegida (`alerts_dispatch`) e script CLI (`includes/process_alerts.php`).
- Separar configuracao local/producao com override opcional (`includes/config.custom.php`) preservando compatibilidade do `includes/config.php`.
- Padronizar migracao para Hostinger com pasta `deploy/` e scripts de geracao de ZIP + backup SQL.

## Integracoes externas
- CDN Tailwind e CDN Chart.js.
- Banco MySQL/MariaDB local/remoto via PDO.
- E-mail transacional via funcao nativa `mail()` do PHP (dependente da configuracao do host).
- Sem APIs externas obrigatorias identificadas.

## Limitacoes do ambiente de execucao
- Sem Composer e sem dependencias externas obrigatorias.
- Parser XLSX depende de `ZipArchive` (se indisponivel, importacao XLSX nao processa).
- Charset/encoding com sinais de inconsistencias em alguns arquivos legados.
- Repositorio Git nao detectado no diretorio atual (`.git` ausente no nivel raiz atual).

## Riscos e pontos frageis
- Roteamento por `switch` cresce com acoplamento e risco de regressao manual.
- Ausencia de testes automatizados.
- Algumas regras de negocio sensiveis estao em models sem cobertura de teste.
- Dependencia de execucao de rotinas para aplicar composicao mensal de divida (nao ha worker continuo nativo).
- Fila OFX em CLI continua dependente de estrategia operacional para selecionar o usuario alvo em ambiente com varios usuarios.

## Pendencias atuais
- Validar padronizacao de encoding em arquivos com texto exibido com mojibake.
- Validar cobertura CSRF para todas as acoes mutaveis restantes.
- Validar necessidade de refatorar regras de dominio para camada de servico.
- Confirmar estrategia de execucao periodica da fila OFX em producao.
- Definir cron de producao para `includes/process_alerts.php` conforme frequencia desejada.
- Implementar provider real de WhatsApp quando credenciais/API estiverem definidas.

## Proximos passos sugeridos
1. Criar checklist de regressao manual por modulo critico (dividas, transacoes, importacao).
2. Padronizar encoding UTF-8 nos arquivos com texto de UI.
3. Definir rotina cron para `includes/process_ofx_queue.php`.
4. Definir rotina cron para `includes/process_alerts.php`.
5. Documentar patch SQL incremental de mudancas de schema fora do `schema.sql`.
6. Iniciar suite minima de testes para regras de divida, deduplicacao OFX e notificacoes.
