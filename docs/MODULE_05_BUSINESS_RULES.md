# Módulo 5 — Contrato funcional de comunicação e comunidade

## 1. Objetivo

Este documento é a fonte de verdade funcional do Módulo 5 do CoSphere. O
módulo encerra o MVP integrando:

- comunicados do condomínio;
- central de notificações dos módulos anteriores;
- enquetes internas;
- revisão integrada das jornadas dos Módulos 1 a 4.

A Etapa M5-00 registra decisões e critérios antes da criação de migrations. Não
faz parte desta etapa criar tabelas, models, rotas ou interfaces.

## 2. Atores

### Administrador

O Administrador ativo e verificado pode:

- acessar as próprias notificações;
- criar e editar comunicados em rascunho;
- publicar comunicados;
- criar e editar enquetes em rascunho;
- publicar e encerrar enquetes;
- consultar somente resultados agregados das enquetes.

### Morador

O Morador ativo e verificado pode:

- acessar somente as próprias notificações;
- consultar comunicados publicados;
- consultar enquetes publicadas;
- votar uma única vez em cada enquete aberta.

### Porteiro

O Porteiro não participa dos fluxos de comunicados e enquetes no MVP. A central
de notificações do Módulo 5 não será disponibilizada a esse perfil, salvo nova
decisão funcional documentada.

## 3. Decisões aprovadas

As decisões abaixo constituem o contrato aprovado para as próximas etapas:

1. A central de notificações será acessível por Administradores e Moradores.
2. Cada usuário visualizará e marcará como lidas somente as próprias notificações.
3. Comunicados serão destinados a Moradores ativos e verificados.
4. Comunicados serão criados como rascunho.
5. Comunicados publicados serão imutáveis no MVP.
6. Publicar o mesmo comunicado mais de uma vez não duplicará notificações.
7. Enquetes serão criadas como rascunho e precisarão ser publicadas.
8. Cada Morador poderá votar uma única vez por enquete.
9. O voto será definitivo e não poderá ser alterado ou removido no MVP.
10. O Administrador visualizará somente resultados agregados, sem lista de votantes.
11. A notificação de encomenda permanecerá exclusiva ao destinatário registrado em `resident_id`.

Qualquer mudança dessas decisões exige atualização deste contrato antes da
alteração do schema ou do comportamento da aplicação.

## 4. Central de notificações

### 4.1 Escopo

A central reunirá em uma única experiência as notificações já produzidas por:

- reservas;
- visitantes;
- encomendas;
- ocorrências e manutenções vinculadas;
- comunicados.

A central não muda as regras de origem desses eventos. Ela apenas oferece uma
consulta comum por destinatário.

### 4.2 Autorização e ownership

- A identidade será sempre obtida da sessão Laravel.
- A listagem começará pelo relacionamento de notificações do usuário autenticado.
- O ID do destinatário nunca será aceito do frontend.
- Marcar como lida resolverá a notificação pelo relacionamento do usuário.
- Uma notificação de outro usuário deverá resultar em recurso não encontrado ou acesso negado, sem revelar seu conteúdo.
- Administradores não terão acesso às notificações de Moradores e vice-versa.

### 4.3 Compatibilidade

Os tipos persistidos `reservation`, `visitor`, `package`, `occurrence` e
`system` serão preservados. O Módulo 5 adicionará um tipo específico para
comunicado por nova migration, sem alterar migrations históricas.

Não será introduzido o canal de database notifications nativo do Laravel. O
MVP continuará usando `App\Models\Notification` e `NotificationService` para
evitar duas centrais incompatíveis.

## 5. Comunicados

### 5.1 Ciclo de vida

```text
RASCUNHO → PUBLICADO
```

- Um comunicado nasce como rascunho.
- Somente rascunhos podem ser editados.
- Publicação é uma transição única e sem retorno no MVP.
- Não haverá despublicação.
- Comunicados publicados não poderão ser editados ou excluídos definitivamente.
- Autor e data de publicação serão definidos pelo backend.

### 5.2 Público e visibilidade

- O público é formado por usuários com papel Morador, ativos e com e-mail verificado.
- A elegibilidade é avaliada no momento da publicação para o envio das notificações.
- Moradores elegíveis no momento da publicação recebem exatamente uma notificação.
- Moradores cadastrados ou verificados posteriormente não recebem notificação retroativa.
- Todo Morador elegível poderá consultar comunicados publicados, inclusive os publicados antes de seu cadastro.
- Rascunhos nunca serão visíveis ao Morador.

### 5.3 Publicação e idempotência

A publicação e a criação das notificações ocorrerão na mesma transação. Uma
falha no fan-out impedirá que o comunicado fique parcialmente publicado.

A idempotência deverá ser garantida no banco e no Service. O contrato esperado
é no máximo uma entrega por combinação de comunicado e destinatário, inclusive
em requisições repetidas ou concorrentes.

## 6. Enquetes

### 6.1 Ciclo de vida

```text
RASCUNHO → PUBLICADA/ABERTA → ENCERRADA
```

- Uma enquete nasce como rascunho.
- Somente rascunhos podem ter pergunta e opções editadas.
- A publicação abre a enquete imediatamente.
- O encerramento será uma ação explícita do Administrador.
- Não haverá agendamento automático de abertura ou encerramento no MVP.
- Enquetes encerradas permanecem disponíveis para consulta de resultado.

### 6.2 Opções

- Uma enquete deve possuir no mínimo duas opções.
- `Sim`, `Não` e `Abster-se` são opções comuns e não possuem tratamento jurídico especial.
- As opções pertencem exclusivamente à sua enquete.
- Pergunta e opções tornam-se imutáveis após a publicação.

### 6.3 Voto

- Somente Morador ativo e verificado pode votar.
- Administrador e Porteiro não votam no MVP.
- A identidade do votante será derivada da sessão.
- Cada Morador pode registrar um único voto por enquete.
- O voto precisa apontar para uma opção pertencente à mesma enquete.
- Enquetes em rascunho ou encerradas rejeitam votos.
- O voto não pode ser editado, substituído ou excluído.
- A unicidade será garantida no banco para resistir a requisições concorrentes.

### 6.4 Resultados

O Administrador visualizará, por opção:

- total de votos;
- percentual sobre os votos registrados;
- total geral de participantes.

O MVP não exibirá identidade, unidade ou lista de votantes. O Morador verá
somente a confirmação de seu voto; a exibição de resultados ao Morador não faz
parte do escopo inicial.

## 7. Matriz de permissões

| Operação | Administrador | Morador | Porteiro |
| --- | --- | --- | --- |
| Consultar as próprias notificações | Sim | Sim | Não |
| Marcar a própria notificação como lida | Sim | Sim | Não |
| Consultar notificação de outro usuário | Não | Não | Não |
| Criar ou editar comunicado em rascunho | Sim | Não | Não |
| Publicar comunicado | Sim | Não | Não |
| Consultar comunicado publicado | Sim | Sim | Não |
| Consultar rascunho de comunicado | Sim | Não | Não |
| Criar ou editar enquete em rascunho | Sim | Não | Não |
| Publicar ou encerrar enquete | Sim | Não | Não |
| Votar em enquete aberta | Não | Sim | Não |
| Consultar resultados agregados | Sim | Não | Não |
| Consultar identidade dos votantes | Não | Não | Não |

## 8. Fluxos aprovados

### 8.1 Comunicado

```text
Administrador cria rascunho
        ↓
Administrador revisa e publica
        ↓
Sistema identifica Moradores elegíveis
        ↓
Publicação e notificações são persistidas atomicamente
        ↓
Morador consulta a central ou a lista de comunicados
```

### 8.2 Enquete

```text
Administrador cria pergunta e opções
        ↓
Administrador publica a enquete
        ↓
Morador seleciona uma opção
        ↓
Backend deriva o usuário e registra um único voto
        ↓
Administrador consulta o resultado agregado
        ↓
Administrador encerra a enquete
```

### 8.3 Integração de notificações

```text
Reserva aprovada ───────────┐
Encomenda recebida ─────────┤
Ocorrência atualizada ──────┼→ Central do destinatário
Novo comunicado ────────────┘
```

## 9. Escopo do Módulo 5

Estão incluídos:

- central compartilhada entre Administrador e Morador;
- listagem paginada e marcação individual como lida;
- criação, edição de rascunho, publicação e consulta de comunicados;
- notificação idempotente de novos comunicados;
- criação, edição de rascunho, publicação e encerramento de enquetes;
- voto único e imutável;
- resultados agregados para Administração;
- integração e regressão dos eventos dos módulos anteriores;
- interfaces responsivas e autorização real no Laravel.

## 10. Fora do escopo

Não serão implementados no MVP:

- blockchain, assinatura digital ou auditoria criptográfica de voto;
- quórum jurídico, procurações ou assembleia formal;
- voto ponderado por unidade ou fração ideal;
- voto anônimo com garantias criptográficas;
- alteração ou exclusão de voto;
- identificação dos votantes nos resultados;
- comentários, reações ou anexos em comunicados;
- segmentação de comunicados por bloco, unidade ou grupos personalizados;
- publicação ou encerramento agendado;
- e-mail, SMS, WhatsApp, push notification ou comunicação em tempo real;
- exportação PDF/Excel e gráficos avançados;
- confirmação de leitura obrigatória;
- central de notificações para Porteiro;
- refatoração ampla dos eventos dos módulos anteriores.

## 11. Requisitos técnicos e de segurança

- Autenticação, papéis e ownership serão validados no Laravel.
- RLS permanece uma POC e não será usada como autorização operacional.
- Controllers serão finos; regras ficarão em Services e consultas em Query Services.
- Form Requests validarão entrada, mas os Services revalidarão invariantes críticas.
- Publicação e voto usarão transações.
- Unicidade de entrega e voto será garantida também por constraints de banco.
- Migrations deverão funcionar em SQLite e PostgreSQL.
- Testes concorrentes serão executados em PostgreSQL para publicação e voto único.
- Nenhum campo de autor, destinatário, votante ou status operacional será confiado ao frontend.

## 12. Estratégia mínima de testes

### Central

- Administrador e Morador acessam a própria central.
- Porteiro permanece bloqueado.
- Listagem e leitura não vazam notificações de outro usuário.
- Tipos dos módulos anteriores continuam visíveis.

### Comunicados

- Somente Administrador cria, edita e publica.
- Rascunho não aparece ao Morador.
- Publicação notifica somente Moradores elegíveis.
- Publicação repetida ou concorrente não duplica notificações.
- Falha de notificação reverte a publicação.
- Publicado é imutável.

### Enquetes

- Somente Administrador gerencia.
- Somente Morador elegível vota.
- Usuário e status enviados pelo cliente são ignorados ou proibidos.
- Opção de outra enquete é rejeitada.
- Rascunho e encerrada rejeitam voto.
- Voto repetido ou concorrente resulta em um único registro.
- Totais e percentuais agregados são consistentes.

## 13. Ambiente e baseline conhecidos

### Ambientes definidos

- `phpunit.xml`: suíte rápida em SQLite `:memory:`.
- `phpunit.module3-pgsql.xml`: integração e concorrência do Módulo 3 em PostgreSQL local na porta `55433`.
- `phpunit.m4-pgsql.xml`: integração e concorrência do Módulo 4 em PostgreSQL local na porta `55434`.
- `.github/workflows/tests.yml`: build, TypeScript, suíte principal e PostgreSQL do Módulo 3 em PHP 8.4 e 8.5.

### Baseline observado antes do Módulo 5

- Testes focados dos fluxos demonstráveis dos Módulos 1 a 4: aprovados.
- Pint, ESLint, Prettier, TypeScript e build de produção: aprovados.
- Suíte SQLite local: 534 testes aprovados e 18 erros de anexos por ausência da extensão GD no PHP local.
- PostgreSQL M3 e M4 local: não executado na última validação porque os serviços nas portas `55433` e `55434` não estavam ativos.
- CI atual provisiona apenas o banco PostgreSQL do Módulo 3; M4 ainda não integra o workflow.
- O `Dockerfile` atual não instala GD nem `pdo_sqlite`, embora essas extensões sejam necessárias para reproduzir integralmente a suíte principal no container.
- O roteiro manual permanece registrado no `README.md` e exige execução humana antes da apresentação.

Esses itens são dívida de ambiente e evidência, não mudanças de regra do Módulo
5. A baseline deve ser estabilizada antes de declarar o módulo concluído.

## 14. Critérios de aceite da Etapa M5-00

- [x] Decisões funcionais registradas.
- [x] Escopo e fora de escopo definidos.
- [x] Fluxos e matriz de permissões definidos.
- [x] Decisões aprovadas como contrato para implementação.
- [x] Ambiente e baseline de testes conhecidos.
- [x] Nenhuma migration ou implementação de domínio criada nesta etapa.

## 15. Próxima etapa

A próxima etapa é **M5-01 — Tornar a central de notificações compartilhada**.
Antes de editar rotas, deve-se preservar o ownership atual por destinatário e
definir a transição da rota exclusiva de Morador para uma rota neutra sem quebrar
links existentes.
