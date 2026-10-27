# CoSphere — validação do Módulo 3 (M3-14)

Data: **30/09/2026**, America/Cuiaba. Base: `3267897b6776bcb260ec49fbcd0499e02295a95b`, branch `develop`, working tree inicialmente limpa e sincronizada com `origin/develop`. Último merge: PR #59, M3-09. `git status` e `git log --oneline -15` foram executados antes das alterações; a extensão do histórico confirmou M3-02–M3-13 integrados (PRs #48–#59).

**Resultado:** homologação automatizada e demonstração no navegador integrado aprovadas, com as ressalvas manuais ao final. Não houve commit ou push. Nenhuma funcionalidade de domínio, dependência ou migration histórica foi alterada.

## Ambiente e linha de base

Windows; PHP 8.5.8; Node 24.18.0; npm 11.16.0; Laravel 13; Inertia 3. A suíte padrão utiliza SQLite `:memory:` em `APP_ENV=testing`. O PostgreSQL foi executado separadamente.

| Verificação                             | Antes das alterações                                                                  | Resultado final                                   |
| --------------------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------------------- |
| `php artisan test` (128 MB)             | Esgotamento de memória durante ResidentExpectedOrderTest/Fortify; sem resumo completo | Limite padrão preservado                          |
| `php artisan test -d memory_limit=512M` | **780 testes / 7.927 asserções**, todos aprovados                                     | **794 testes / 8.460 asserções**, todos aprovados |
| `npm run types:check`                   | Aprovado                                                                              | Aprovado                                          |
| `npm run lint:check`                    | Aprovado                                                                              | Aprovado                                          |
| `npm run format:check`                  | Aprovado                                                                              | Aprovado                                          |
| `npm run build`                         | Aprovado                                                                              | Aprovado                                          |
| `vendor/bin/pint --test`                | Aprovado                                                                              | Aprovado                                          |
| `git diff --check`                      | Aprovado                                                                              | Aprovado                                          |

Não houve falhas funcionais pré-existentes na execução com 512 MB. O build apresentou aviso de tempo gasto por plugins; não impediu a compilação. Nenhum `php.ini` global foi alterado. A linha de base completa levou aproximadamente 10min38s; após isolar o SSR externo nos testes, a execução final levou aproximadamente 47s.

## Rastreabilidade dos requisitos

Fonte dos RFs: texto do documento do TCC fornecido pelo solicitante nesta validação. Os critérios operacionais são os do card M3-14 e de `docs/module-3-business-rules.md`; o texto integral da seção 1.6 não estava no repositório nem foi fornecido.

| RF    | Critério fornecido                                                    | Evidência executada                                                                                                                                                                                       |
| ----- | --------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RF007 | Disponibilidade, reservas por data/horário e prevenção de conflitos   | CommonAreaAvailabilityTest, ReservationCreationTest, Module3IntegrationTest; disputa reserva × reserva no PostgreSQL; calendário e conflito pela UI                                                       |
| RF008 | Regras de uso, limites, horários, duração e confirmação automática    | CommonAreaManagementTest e ReservationCreationTest; jornada automática integrada e UI. Limite implementado: duração configurada, mesmo dia e funcionamento; não há capacidade máxima no contrato anterior |
| RF009 | Notificações automáticas com dados da reserva                         | ReservationNotificationTest e jornadas integradas; título, resultado, área, data, horário, destinatário, contagem, inbox e leitura                                                                        |
| RF010 | Previsão pelo Morador e recebimento previsto/não previsto na portaria | ResidentExpectedOrderTest, PortariaOrderTest, quatro jornadas integradas e ambos os recebimentos pela UI                                                                                                  |
| RF011 | Aviso de chegada e registro de retirada                               | NotificationServiceTest, PortariaOrderTest, OrderPickupTest; retirada concorrente no PostgreSQL; aviso e retirada pela UI                                                                                 |
| RF012 | Consulta de status e histórico de entregas por unidade                | OrderHistoryTest, jornadas integradas, filtros/detalhes pela UI e consulta de encomenda retirada                                                                                                          |

A aprovação automática do RF008 foi comprovada com `requires_approval=false`. A aprovação manual por configuração de área é um contrato adicional já adotado em M3-03–M3-06 e foi preservado.

## Implementação real auditada

Os arquivos a seguir são relativos à raiz do projeto. Controllers e Requests estão em `app/Http/`, Services em `app/Services/`, Models em `app/Models/` e testes em `tests/Feature/`.

| Card  | Backend / contrato                                                                                                                            | Inertia / cobertura existente                                                              |
| ----- | --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| M3-02 | Notification; NotificationService (`create`, `paginateForUser`, `markAsRead`); NotificationController (`index`, `read`)                       | `morador/notifications/index.tsx`; NotificationFeatureTest, NotificationServiceTest        |
| M3-03 | CommonAreaController; Store/UpdateCommonAreaRequest; CommonArea                                                                               | `admin/common-areas.tsx`; CommonAreaManagementTest                                         |
| M3-04 | ResidentCommonAreaController; CommonAreaAvailabilityRequest; ReservationService                                                               | `morador/common-areas.tsx`; CommonAreaAvailabilityTest                                     |
| M3-05 | ReservationController; StoreReservationRequest; ReservationService; Reservation                                                               | formulário na página de áreas; ReservationCreationTest                                     |
| M3-06 | AdminReservationController/ReservationController; UpdateReservationRequest, RejectReservationRequest; ReservationService                      | listas e detalhes Admin/Morador; ReservationLifecycleTest, ReservationQueryTest            |
| M3-07 | CommonAreaBlockController; StoreCommonAreaBlockRequest; CommonAreaBlockService; CommonAreaBlock                                               | `admin/common-area-blocks.tsx`; CommonAreaBlockTest                                        |
| M3-08 | ReservationStatusHistory; ReservationQueryService; escrita transacional por ReservationService                                                | `admin/reservation-details.tsx`, `morador/reservation-details.tsx`; ReservationHistoryTest |
| M3-09 | ReservationService utiliza a infraestrutura M3-02                                                                                             | inbox compartilhada; ReservationNotificationTest                                           |
| M3-10 | ResidentOrderController; StoreExpectedOrderRequest; OrderService; Order                                                                       | `morador/orders/index.tsx`; ResidentExpectedOrderTest                                      |
| M3-11 | PortariaOrderController; StoreUnexpectedOrderRequest, ReceiveOrderRequest, IndexPortariaOrderRequest; OrderService, PortariaOrderQueryService | `portaria/orders/index.tsx`; PortariaOrderTest                                             |
| M3-12 | pickup nos controllers Morador/Portaria; PickupOrderRequest; OrderService                                                                     | listas/detalhes de encomendas; OrderPickupTest                                             |
| M3-13 | ResidentOrderController, PortariaOrderHistoryController; IndexOrderHistoryRequest; OrderHistoryService                                        | `orders/show.tsx`, `portaria/order-history/index.tsx`; OrderHistoryTest                    |

Migrations reais: `2026_05_17_231949_create_common_areas_table`, `2026_05_17_232039_create_reservations_table`, `2026_05_17_233717_create_notifications_table`, `2026_05_20_144131_create_orders_table`, `2026_09_29_223002_create_common_area_blocks_table` e `2026_09_30_031128_create_reservation_status_histories_table`. M3-10–M3-13 utilizam a tabela `orders` existente; não há migration separada de eventos de encomendas.

### Estados, autoria e histórico

- Reservation: `Pending=pending`, `Approved=confirmed`, `Rejected=rejected`, `Cancelled=cancelled`. `Completed=completed` é estado legado, sem novo fluxo de conclusão. Pending e Approved ocupam a agenda; recusa/cancelamento liberam o período e preservam o registro.
- History de reserva: evento inicial e transições válidas, ordenados por `created_at` e ID, com ator, role e motivo. `updated_at` não é timeline. Reservas antigas sem History continuam consultáveis com histórico vazio, sem criação de eventos artificiais.
- Encomenda é `Order`: `WaitingDelivery=waiting_delivery` → `ReceivedAtGate=received_at_gate` → `PickedUp=picked_up`. `Cancelled=cancelled` existe no enum/Service e na consulta; não existe rota de cancelamento neste escopo.
- Previsão obtém Morador e unidade da sessão. Não existe data de entrega informada pelo usuário nesse formulário; `expected_delivery_date` é rejeitado pelo Request. Recebimento previsto atualiza o mesmo Order. Recebimento inesperado cria diretamente ReceivedAtGate, com destinatário coerente com a unidade.
- Retirada é uma única transição, executável pelo Porteiro **ou por Morador ativo da mesma unidade original**. Não existe confirmação adicional obrigatória. `picked_up_by_id` identifica quem registrou a operação; não afirma quem recolheu fisicamente a caixa. A primeira operação válida preserva operador/timestamp; duplicidade é recusada.
- Histórico de encomenda utiliza status, `created_at`, `received_at`, `picked_up_at` e respectivos operadores. Não existe tabela de eventos independente. Estados finalizados permanecem consultáveis; o contrato permite visualizar encomendas de outro Morador da mesma unidade.
- Notificações de ambas as trilhas usam Notification/NotificationService M3-02. Previsão e retirada não criam avisos novos. Recebimento cria exatamente um aviso ao `resident_id` original, mesmo quando há outros moradores na unidade.

### Rotas, infraestrutura e permissões

`php artisan route:list` e `routes/web.php` foram conferidos. Os grupos M3 utilizam `auth` → `active` → `verified` → `role:admin|morador|porteiro`. As Feature Tests exercitam Route → Middleware → FormRequest quando existente → Controller → Service → DB → JSON/redirect/Inertia. Leituras sem FormRequest e inbox não receberam uma camada artificial.

| Perfil / escopo                              | Contrato confirmado por HTTP                                                                                                                             |
| -------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Admin                                        | Gestão de áreas/bloqueios e análise/cancelamento/consulta de reservas; não ganha operações de Morador/Portaria por conhecer a URL                        |
| Morador                                      | Cria e consulta suas próprias reservas por `user_id`; nem o colega da mesma Unit pode operar a reserva. Encomendas são compartilhadas pela Unit original |
| Porteiro                                     | Recebimento, retirada e histórico de encomendas; sem gestão de reservas                                                                                  |
| Guest, inativo, não verificado, papel errado | Redirecionamento ou recusa conforme middleware; operações críticas cobertas nos testes existentes                                                        |
| Unidade diferente                            | Consulta/retirada de encomenda indevida bloqueada; recursos privados de reserva/inbox também bloqueados                                                  |
| Notificação                                  | Listagem e leitura exclusivas de `recipient_id`, inclusive contra colega da mesma Unit                                                                   |

Payloads indevidos de `user_id`, `unit_id`, `recipient_id`, `status` e IDs de operadores/admin foram conferidos na cobertura existente: valores são rejeitados ou ignorados conforme cada Request, e autoria/unidade operacional é resolvida pelo servidor. Foi executada a suíte completa de autenticação, verificação, usuários, perfil, role middleware, active middleware, vínculo User→Unit e isolamento; não somente testes de Services.

## Cenários integrados executados

| Fluxo                       | Resultado e evidência                                                                                                      |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| 1. Automática               | HTTP/Inertia → Approved, usuário/unidade/horário corretos, History inicial, um aviso e agenda ocupada                      |
| 2. Manual + aprovação       | Pending ocupa agenda; Admin aprova; History e aviso correspondentes; período continua ocupado                              |
| 3. Recusa                   | Motivo/ator/histórico/aviso corretos; período livre; Reservation preservada                                                |
| 4. Cancelamento             | Pending e Approved cancelados pelos atores permitidos; agenda livre e registro preservado. Ação própria não duplica aviso  |
| 5. Conflito                 | 14–16 impede 15–17 por HTTP; 16–18 aceito. Contagens de reserva/History/avisos corretas                                    |
| 6. Bloqueio                 | Criação, calendário indisponível, solicitação e aprovação defensiva recusadas, remoção e recálculo                         |
| 7. Histórico                | Sequência, status, ator, role, motivo e legado sem History; timeline independente de updated_at                            |
| 8. Notificações             | Tipo, título, mensagem, dados, destinatário e exatamente um aviso por evento aplicável; erro/rollback sem aviso de sucesso |
| 9. Encomenda prevista       | Forecast → recebimento no mesmo Order → aviso → consulta → retirada; sem segunda encomenda                                 |
| 10. Não prevista            | Porteiro cria ReceivedAtGate com unidade/destinatário/operador/timestamp corretos; aviso e consulta                        |
| 11. Retirada                | Porteiro e Morador da mesma unidade, primeira transição preservada, duplicidade recusada, sem aviso extra                  |
| 12. Histórico de encomendas | Escopo por unidade, timestamps/operadores, filtros e registros terminais preservados                                       |

O contrato M3-07 foi preservado: **Pending/Approved conflitante existente impede criar Block**; nenhuma reserva é cancelada automaticamente. A nova jornada cobre Pending, e CommonAreaBlockTest cobre também Approved. Rollback e operações inválidas já tinham testes focais em reservas/encomendas; foram reexecutados em SQLite e PostgreSQL, sem duplicar essa cobertura.

## PostgreSQL real

PostgreSQL **18.3**, cluster temporário próprio em `%TEMP%/cosphere-m3-14-pg`, ouvindo somente `127.0.0.1:55433`. Banco dedicado de testes: **cosphere_m3_test**. Banco separado de demo: **cosphere_m3_demo**. `APP_ENV=testing`, host e identidade do banco foram confirmados antes das migrations. O Docker local estava indisponível; foram usados os binários PostgreSQL já instalados e um cluster novo, sem alterar o serviço PostgreSQL existente.

O banco externo da aplicação no Supabase não recebeu migrations, seed ou testes destrutivos. Credenciais não estão neste relatório. A configuração de testes bloqueia DB_URL, ambiente/host/database inesperados e cache de configuração de desenvolvimento antes de inicializar Laravel.

- Todas as **26 migrations**, desde zero, aplicadas com sucesso nos bancos locais dedicados, incluindo blocks, reservation histories, orders e notifications.
- Rollback seguro das duas últimas migrations (M3-08 e M3-07, tabelas vazias), reaplicação e `php artisan migrate:status`: **26 Ran**.
- Bateria separada `php artisan test -c phpunit.module3-pgsql.xml -d memory_limit=512M`: **465 testes / 4.289 asserções**, todos aprovados; inclui queries, constraints, casts, datas, filtros e os quatro testes concorrentes.
- Sem incompatibilidade real de query/migration encontrada; nenhuma migration histórica foi editada.

Após a última bateria, `migrate:status` novamente confirmou as 26 migrations aplicadas. O cluster temporário foi encerrado ao final, preservando os dados locais; o comando de retomada consta na preparação da demo.

| Concorrência real                            | Resultado obrigatório observado                                                                           |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Reserva × reserva, mesma área/14–16          | Uma resposta 201, uma 422; uma Reservation, um History inicial, um aviso ao vencedor                      |
| Reserva × Block, mesma área/14–16            | Uma resposta 201, uma 422; apenas reserva **ou** block persistido; segunda operação revalida              |
| Approve × Reject da mesma Pending            | Uma resposta 200, uma 422; uma transição, um History e um aviso, ator/resultado do vencedor               |
| Porteiro × Morador da mesma Unit na retirada | Um redirect 302, uma 422; um estado PickedUp, operador/timestamp vencedor preservados, nenhum aviso extra |

Os workers atendem rotas reais pelo Kernel HTTP, em **dois processos PHP e duas conexões/transações independentes**. O teste segura o registro-alvo, confirma ambos os PIDs distintos aguardando `wait_event_type='Lock'` em `pg_stat_activity` e então libera o lock. Assim, a sobreposição concorrente é comprovada, em vez de presumida por execução sequencial. O login de cada worker é fornecido pelo guard de teste; os contratos de login são testados separadamente. SQLite continua sendo regressão determinística e não constitui evidência de `SELECT ... FOR UPDATE`.

Reexecução: com esse banco local exclusivo disponível, executar o comando acima. A porta e o usuário podem ser fornecidos pelo ambiente; database/host/ambiente permanecem restritos. Essa configuração é destrutiva **somente para cosphere_m3_test**.

## Lacunas preenchidas e ajustes

| Problema / origem                                                                                                                                                                                         | Menor ajuste                                                                                                 | Regressão / verificação                                                                                                                                               |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Cards tinham boa cobertura por operação, mas faltava jornada única atravessando calendário, lifecycle, History e inbox; encomendas precisavam ligar recebimento à consulta terminal e isolamento do aviso | `tests/Feature/Module3IntegrationTest.php`                                                                   | **9 testes / 512 asserções**, rotas e Inertia reais, sem duplicar toda matriz anterior                                                                                |
| SQLite não comprova locking PostgreSQL                                                                                                                                                                    | `tests/Integration/Module3/{Module3ConcurrencyTest,http-worker,bootstrap}.php` e `phpunit.module3-pgsql.xml` | Quatro disputas HTTP independentes; bateria PostgreSQL completa aprovada                                                                                              |
| Tests utilizavam SSR externo ativo/configuração Vite local, causando dependência de rede e demora                                                                                                         | `tests/TestCase.php` desabilita SSR real apenas no ambiente de testes                                        | Novo caso em InertiaRequestIsolationTest impede stray requests; o caso existente com Gateway mock continua passando (**2 testes / 8 asserções**)                      |
| CI não verificava TypeScript, utilizava comandos de lint/format que corrigiam arquivos e estava vulnerável ao OOM observado em 128 MB                                                                     | `.github/workflows/tests.yml`, `.github/workflows/lint.yml`                                                  | Comandos equivalentes executados localmente, YAML parseado; 512 MB justificados pela linha de base, checks sem mutação, serviço PostgreSQL isolado e bateria separada |
| Faltavam dados previsíveis e protegidos para apresentação                                                                                                                                                 | `database/seeders/Module3DemoSeeder.php`, `config/module3-demo.php`                                          | Module3DemoSeederTest: **4 testes / 19 asserções**, repetição, recusa de produção/banco compartilhado e senha externa obrigatória                                     |

Não foi encontrado bug de domínio que exigisse modificar Controllers, Requests, Services, Models ou páginas de produção. Novos testes na suíte padrão: **14**; concorrência: **4 adicionais na configuração PostgreSQL separada**. Pint com `--dirty --format agent` foi executado nos PHP alterados; checks finais completos foram executados sequencialmente.

## Frontend efetivamente observado

Navegador disponível: **Codex In-app Browser**. Chrome, Firefox e Edge não estavam disponíveis para automação nesta sessão; não são considerados homologados. Dados sintéticos no banco local de demo, páginas Inertia reais e assets compilados. Um router temporário isolou o hot file/SSR do servidor de QA; `public/hot` e `.env` do desenvolvedor foram preservados.

Viewports: **360×800** mobile e **1280×800** desktop para páginas/ações principais; **768×1024** tablet para inbox. Nenhum overflow horizontal nas páginas verificadas. Menus Admin/Morador/Porteiro, labels, cards, filtros, disponibilidade, timeline, dialogs, mensagens de sucesso/erro, ausência de ações em estado terminal e fechamento por Escape foram conferidos. Estado de consulta/loading e botões desabilitados existem; resistência a duplicidade é comprovada pelos testes HTTP/concorrentes, não por captura visual de um loading transitório. Console de erros/warnings no navegador após os fluxos: vazio.

| Cenário                                                 | Chrome   | Firefox  | Edge     | Mobile                                    | Resultado                                            |
| ------------------------------------------------------- | -------- | -------- | -------- | ----------------------------------------- | ---------------------------------------------------- |
| A. Reserva automática, status, History e inbox          | Pendente | Pendente | Pendente | Executado no IAB                          | Aprovado no IAB; criação e consulta real             |
| B. Reserva manual e aprovação Admin                     | Pendente | Pendente | Pendente | Dialog Admin executado                    | Aprovado no IAB; desktop + dialog mobile             |
| C. Tentativa 15–17 sobre reserva 14–16                  | Pendente | Pendente | Pendente | Pendente para essa tentativa              | Recusada no IAB desktop; mensagem compreensível      |
| D. Block 18–20 e tentativa recusada                     | Pendente | Pendente | Pendente | Criação e calendário/erro observados      | Aprovado no IAB; tentativa enviada no desktop        |
| E. Previsão → recebimento do mesmo Order → aviso        | Pendente | Pendente | Pendente | Formulário/listas observados              | Aprovado no IAB; recepção e aviso reais              |
| F. Não prevista → recebimento → retirada → histórico    | Pendente | Pendente | Pendente | Dialog de retirada e histórico executados | Aprovado no IAB; terminal consultável                |
| Inbox: marcar como lida e persistir após novo login     | Pendente | Pendente | Pendente | Mobile e tablet observados                | Aprovado no IAB                                      |
| Recusa/cancelamento/remover Block/retirada pelo Morador | Pendente | Pendente | Pendente | Pendente                                  | Automatizado aprovado; UI não executada nesta sessão |

Evidências salvas em `docs/module-3-evidence/`: reserva automática/histórico mobile, reserva pendente desktop, bloqueio mobile, disponibilidade/bloqueio desktop e mobile, encomenda retirada desktop e mobile e inbox tablet. Capturas comprovam somente o estado/superfície que mostram; não substituem os testes ou a revisão humana.

## Dados e roteiro de demonstração

Seeder dedicado, fora de DatabaseSeeder de produção, executado no banco **cosphere_m3_demo**. Após a validação visual, esse banco foi reinicializado: cinco usuários ativos/verificados, duas unidades, duas áreas, uma previsão, uma encomenda recebida, um aviso inicial, zero reservas e zero blocks. O servidor HTTP temporário foi encerrado. Para escolher a senha temporária da apresentação, executar a preparação abaixo. Executar apenas o seeder novamente preserva registros existentes, não desfaz operações nem troca senhas.

| Login sintético                       | Perfil / escopo                                                                        |
| ------------------------------------- | -------------------------------------------------------------------------------------- |
| `admin@m3-demo.cosphere.test`         | Admin                                                                                  |
| `morador@m3-demo.cosphere.test`       | Morador, DEMO/101                                                                      |
| `porteiro@m3-demo.cosphere.test`      | Porteiro                                                                               |
| `vizinho@m3-demo.cosphere.test`       | Morador, DEMO/101, para escopo compartilhado de encomendas e privado de reservas/inbox |
| `outra-unidade@m3-demo.cosphere.test` | Morador, DEMO/102, para isolamento                                                     |

Áreas: **Quiosque Demo** (automática) e **Salão Demo** (manual), 08–22h, duração máxima 240 min. Encomendas: **DEMO-PREVISTA** (WaitingDelivery) e **DEMO-RECEBIDA** (ReceivedAtGate). Senha temporária exclusiva fornecida por `MODULE3_DEMO_PASSWORD` em runtime, sem senha real armazenada no código/documentação. O seeder aceita somente banco PostgreSQL local com nome exato cosphere_m3_demo, ou SQLite em memória nos testes.

### Preparação segura da demo (PowerShell)

Utilizar o cluster local exclusivo descrito acima. Antes de migrar, verificar identidade; nunca reaproveitar a conexão Supabase. O exemplo recria **somente o banco local de demo descartável**:

```powershell
$env:APP_ENV = 'testing'
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP ('m3-demo-config-' + [guid]::NewGuid() + '.php')
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '55433'
$env:DB_DATABASE = 'cosphere_m3_demo'
$env:DB_USERNAME = 'cosphere_m3_test'
$env:DB_PASSWORD = ''
$env:DB_URL = ''
$env:DB_SSLMODE = 'disable'
$pgBin = 'C:\Program Files\PostgreSQL\18\bin'
$identity = & "$pgBin\psql.exe" -h 127.0.0.1 -p 55433 -U cosphere_m3_test -d cosphere_m3_demo -Atc 'SELECT current_database()'
if ($LASTEXITCODE -ne 0 -or $identity -ne 'cosphere_m3_demo' -or $env:APP_ENV -ne 'testing') { throw 'Banco de demo não confirmado' }
$env:MODULE3_DEMO_PASSWORD = Read-Host 'Senha temporária exclusiva da demo (mínimo 12 caracteres)' -MaskInput
php artisan migrate:fresh --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Migrations falharam' }
php artisan db:seed --class=Module3DemoSeeder --no-interaction
Remove-Item Env:MODULE3_DEMO_PASSWORD
```

Para retomar o cluster já preparado, se estiver parado: `pg_ctl.exe -D "$env:TEMP\cosphere-m3-14-pg" -l "$env:TEMP\cosphere-m3-14-pg\server.log" -o "-h 127.0.0.1 -p 55433" -w start`, utilizando os binários acima. Esse diretório é temporário e pode ser removido pelo sistema. Manter as variáveis de banco apenas no terminal da demo; usar `SESSION_DRIVER=file`, iniciar a aplicação e Vite nesse ambiente local. Não versionar `.env` ou senha.

### Apresentação curta

Usar uma data futura D (na validação: 01/10/2026). Reiniciar os dados de demo antes da apresentação e usar rastreios novos para os cadastros ao vivo.

1. **A — Automática:** Morador abre Quiosque, D, reserva 14–16 → Aprovada → inbox com dados → detalhes/History → agenda ocupada.
2. **B — Manual:** Morador solicita Salão, D, 14–16 → Pendente. Admin abre Reservas e aprova. Morador confere Aprovada, aviso e dois eventos no History.
3. **C — Conflito:** Morador tenta Quiosque, D, 15–17 → erro de indisponibilidade; 16–18 é consecutivo válido (automatizado).
4. **D — Manutenção:** Admin bloqueia Quiosque, D, 18–20. Morador vê Indisponível e tentativa recusada. Para extensão, remover Block e conferir período livre.
5. **E — Prevista:** Morador cadastra previsão ou usa DEMO-PREVISTA. Porteiro recebe a mesma encomenda. Morador confere aviso, status e operador/horário.
6. **F — Não prevista:** Porteiro seleciona DEMO/101 e Morador Demo, cadastra rastreio novo → Recebida. Morador confere aviso. Porteiro confirma retirada → Retirada → Histórico/detalhes preservados. Não há segunda confirmação obrigatória.

### Revisão cruzada humana — ainda não executada

**Sergio executa Encomendas:**

- [ ] Executar E/F em Chrome, Firefox e Edge; anotar versão, data e resultado.
- [ ] Conferir unidade, destinatário do aviso, mesmo ID da previsão, horários/operadores e terminal consultável.
- [ ] Repetir retirada: recusada; tentar outra Unit: bloqueada; colega da Unit: encomenda permitida, inbox privado.
- [ ] Conferir mobile/tablet reais, campos, dialogs, erro/sucesso, loading, filtros, vazio e acessibilidade básica.

**Yasmin executa Reservas:**

- [ ] Executar A/B/C/D em Chrome, Firefox e Edge; anotar versão, data e resultado.
- [ ] Recusar com motivo e cancelar Pending/Approved; conferir período livre, History e avisos aplicáveis.
- [ ] Tentar Block sobre Pending/Approved: recusado sem cancelamento automático; remover Block e conferir disponibilidade.
- [ ] Tentar reserva de outro usuário, inclusive colega da Unit: bloqueada; conferir mobile/tablet, timeline e dialogs.

**Ambos:**

- [ ] Conferir formalmente a seção 1.6 do documento original e registrar o aceite humano de usabilidade/responsividade/compatibilidade.

## Pendências reais

- Execução interativa em **Chrome, Firefox e Edge**; validação em dispositivos físicos e tablet além da inbox.
- Passos UI explicitamente pendentes na tabela; a cobertura automatizada correspondente passou.
- Revisão humana de **Sergio/Yasmin**, incluindo seção 1.6 e aceite dos RNFs. A IA não assina esse aceite.
- Execução hospedada dos workflows GitHub Actions alterados; comandos e sintaxe foram conferidos localmente, sem commit/push.

O aceite técnico automatizado está documentado; o encerramento formal do M3-14 deve registrar as pendências humanas acima como executadas ou como ressalvas aceitas pela equipe.
