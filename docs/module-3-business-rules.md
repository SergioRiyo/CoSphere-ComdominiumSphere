# Módulo 3 — Regras de Negócio

## 1. Objetivo

O Módulo 3 do CoSphere contempla dois domínios principais:

1. **Reservas de áreas comuns**
2. **Controle de encomendas**

O módulo também utiliza o sistema de **notificações internas** para comunicar eventos relevantes aos usuários.

O objetivo deste documento é definir as regras de negócio, permissões, estados, transições e restrições que devem ser respeitadas durante a implementação dos cards do Módulo 3.

Este documento representa o contrato funcional do módulo.

As regras devem ser aplicadas principalmente no backend. A interface pode antecipar validações para melhorar a experiência do usuário, mas não deve ser considerada mecanismo de segurança ou fonte final das regras de negócio.

---

# 2. Perfis envolvidos

Os perfis existentes no sistema continuam sendo:

- Administrador
- Morador
- Porteiro

Cada domínio possui responsabilidades diferentes.

## 2.1 Administrador

No contexto de reservas, o Administrador é responsável por:

- cadastrar e editar áreas comuns;
- ativar e inativar áreas comuns;
- consultar reservas;
- aprovar solicitações;
- recusar solicitações;
- cancelar reservas quando necessário;
- criar bloqueios de datas e horários;
- consultar histórico de reservas.

O Administrador não solicita reservas como Morador.

No fluxo de encomendas, o Administrador não participa das operações de recebimento e retirada neste módulo.

---

## 2.2 Morador

No contexto de reservas, o Morador pode:

- consultar áreas comuns ativas;
- consultar disponibilidade;
- solicitar reserva;
- consultar suas reservas;
- consultar status;
- cancelar sua própria reserva quando permitido.

No contexto de encomendas, o Morador pode:

- cadastrar uma encomenda prevista;
- consultar encomendas da própria unidade;
- consultar status e histórico das encomendas.

O Morador não pode:

- aprovar reservas;
- recusar reservas;
- criar bloqueios;
- registrar recebimento de encomendas;
- confirmar retirada de encomendas.

---

## 2.3 Porteiro

O Porteiro não participa da gestão de reservas.

No fluxo de encomendas, o Porteiro pode:

- registrar recebimento;
- identificar a unidade destinatária;
- associar o recebimento a uma previsão existente quando aplicável;
- registrar/confirmar retirada;
- consultar informações operacionais necessárias às encomendas.

O Porteiro não pode acessar funcionalidades exclusivas do Morador ou do Administrador apenas por possuir usuário autenticado.

---

# 3. Regras gerais de autorização

Todas as operações devem validar no backend:

- usuário autenticado;
- usuário ativo;
- usuário verificado quando exigido pelo padrão atual do sistema;
- papel correto para a operação.

A interface não substitui autorização backend.

IDs enviados pelo frontend não devem ser considerados fonte de verdade quando a informação puder ser obtida pela sessão.

Exemplos:

- `resident_id` deve ser derivado do Morador autenticado;
- unidade do Morador deve ser derivada de seu vínculo atual;
- operador que recebe uma encomenda deve ser derivado do Porteiro autenticado;
- operador que confirma uma retirada deve ser derivado do Porteiro autenticado.

---

# 4. Isolamento por unidade

As regras de isolamento utilizadas pelo restante do CoSphere também se aplicam ao Módulo 3.

Um Morador só pode operar sobre dados pertencentes à sua própria unidade.

Exemplo:

```text
Morador da Unidade A
→ não pode consultar reserva privada ou encomenda da Unidade B
```

Nunca confiar em `unit_id` recebido do frontend quando a unidade puder ser obtida através do usuário autenticado.

Consultas, Policies, Services e Controllers devem preservar esse isolamento.

---

# 5. Reservas de áreas comuns

---

## 5.1 Área comum

Uma área comum representa um espaço do condomínio que pode possuir disponibilidade para reservas.

Uma área pode possuir informações como:

- nome;
- descrição;
- capacidade;
- regras de utilização;
- horário inicial de funcionamento;
- horário final de funcionamento;
- status ativo/inativo.

A implementação deve reaproveitar os campos já existentes em `CommonArea` quando aplicável.

Não devem ser adicionados campos apenas para aumentar artificialmente a complexidade do módulo.

---

## 5.2 Área ativa

Somente áreas ativas podem receber novas solicitações de reserva.

```text
Área ativa
→ pode receber novas reservas
```

```text
Área inativa
→ não pode receber novas reservas
```

Inativar uma área não deve apagar seu histórico.

Reservas antigas devem continuar disponíveis para consulta.

---

# 6. Estados de uma reserva

O fluxo utilizará os seguintes estados de negócio:

```text
PENDING
APPROVED
REJECTED
CANCELED
```

Os nomes concretos do Enum podem seguir o padrão já utilizado pelo projeto.

---

## 6.1 PENDING

Representa uma solicitação criada pelo Morador e aguardando decisão administrativa.

---

## 6.2 APPROVED

Representa uma reserva autorizada pelo Administrador.

---

## 6.3 REJECTED

Representa uma solicitação recusada pelo Administrador.

É um estado terminal.

---

## 6.4 CANCELED

Representa uma reserva ou solicitação cancelada.

É um estado terminal.

---

# 7. Transições de reserva

Transições permitidas:

```text
PENDING
├── APPROVED
├── REJECTED
└── CANCELED
```

Uma reserva aprovada também pode ser cancelada:

```text
APPROVED
└── CANCELED
```

Não permitir:

```text
REJECTED → APPROVED
CANCELED → APPROVED
REJECTED → PENDING
CANCELED → PENDING
```

Alterações desse tipo exigiriam uma nova solicitação.

---

# 8. Solicitação de reserva

Somente Morador pode solicitar reserva.

Para criar uma solicitação:

- Morador deve estar ativo;
- Morador deve possuir unidade válida;
- área deve existir;
- área deve estar ativa;
- início deve ser anterior ao fim;
- intervalo deve respeitar disponibilidade da área;
- intervalo não pode conflitar com bloqueios;
- intervalo não pode conflitar com reservas consideradas ocupantes daquele horário.

O Morador e sua unidade devem ser derivados da sessão.

---

# 9. Datas e horários

Toda reserva deve possuir intervalo válido:

```text
start_at < end_at
```

Não permitir intervalo completamente no passado.

Quando a área possuir horário de funcionamento:

```text
opening_time <= start_at
```

e:

```text
end_at <= closing_time
```

A aplicação deve interpretar datas e horários segundo a política de timezone definida pelo projeto.

O frontend não deve converter silenciosamente `datetime-local` para UTC de maneira que altere o horário escolhido pelo usuário.

---

# 10. Regra de conflito

A prevenção de conflito é uma das principais regras do domínio de reservas.

Duas reservas da mesma área entram em conflito quando:

```text
novo_inicio < reserva_existente_fim
AND
novo_fim > reserva_existente_inicio
```

Exemplo:

```text
Reserva existente:
14:00 -------- 16:00

Nova:
15:00 -------- 17:00

CONFLITO
```

Também é conflito:

```text
13:00 -------- 15:00
```

Não é conflito:

```text
16:00 -------- 18:00
```

Intervalos adjacentes são permitidos.

---

# 11. Estados que bloqueiam disponibilidade

Para simplificar o fluxo e evitar múltiplas solicitações concorrentes para o mesmo horário, reservas nos estados:

```text
PENDING
APPROVED
```

ocupam o intervalo para fins de nova solicitação.

Reservas:

```text
REJECTED
CANCELED
```

não bloqueiam disponibilidade.

Dessa forma:

```text
PENDING → horário indisponível
APPROVED → horário indisponível
REJECTED → horário disponível
CANCELED → horário disponível
```

---

# 12. Revalidação de conflito

A ausência de conflito deve ser validada no backend.

A regra precisa ser verificada:

1. ao solicitar a reserva;
2. novamente quando houver uma operação capaz de tornar a reserva efetiva, principalmente aprovação quando necessário.

O calendário no frontend é apenas representação visual.

Ele não substitui a verificação no backend.

---

# 13. Concorrência em reservas

Duas requisições simultâneas não devem conseguir criar reservas conflitantes para a mesma área.

A implementação dos cards posteriores deve considerar:

- transação;
- revalidação no backend;
- locks ou estratégia equivalente quando necessário.

O M3-01 define a regra, mas não exige implementação específica.

---

# 14. Aprovação de reserva

Somente Administrador pode aprovar uma reserva.

Apenas reservas:

```text
PENDING
```

podem ser aprovadas.

Antes da aprovação, o sistema deve confirmar novamente:

- área ainda existe;
- área ainda está ativa;
- intervalo continua válido;
- não existe conflito;
- não existe bloqueio sobre o período.

Resultado:

```text
PENDING → APPROVED
```

---

# 15. Recusa de reserva

Somente Administrador pode recusar uma solicitação.

Apenas reservas:

```text
PENDING
```

podem ser recusadas.

Resultado:

```text
PENDING → REJECTED
```

Reserva recusada permanece disponível no histórico.

Ela não pode voltar para `PENDING`.

---

# 16. Cancelamento de reserva

O Morador pode cancelar sua própria reserva quando ela estiver:

```text
PENDING
```

ou:

```text
APPROVED
```

desde que ainda não tenha iniciado.

O Administrador também pode cancelar reservas quando necessário.

Resultado:

```text
PENDING → CANCELED
```

ou:

```text
APPROVED → CANCELED
```

Reservas:

```text
REJECTED
CANCELED
```

não podem ser canceladas novamente.

O histórico não deve ser apagado.

---

# 17. Reserva passada

Não será necessário criar um estado persistido `COMPLETED`.

Uma reserva:

```text
status = APPROVED
end_at < agora
```

pode ser apresentada visualmente como:

```text
Concluída
```

mas seu status persistido continua `APPROVED`.

Isso evita uma transição automática desnecessária apenas por passagem do tempo.

---

# 18. Bloqueios de área comum

O Administrador pode tornar uma área indisponível durante determinado intervalo.

Um bloqueio deve possuir conceitualmente:

- área;
- início;
- fim;
- motivo;
- responsável pela criação.

A implementação concreta pode adaptar essa estrutura à arquitetura existente.

---

# 19. Regra de bloqueio

Durante um bloqueio:

```text
nenhuma nova reserva pode ser solicitada
```

para o intervalo correspondente.

A mesma regra de sobreposição utilizada nas reservas deve ser utilizada para verificar bloqueios.

---

# 20. Conflito entre bloqueio e reservas existentes

Um bloqueio não deve sobrescrever silenciosamente reservas existentes.

Se existir reserva:

```text
PENDING
```

ou:

```text
APPROVED
```

no mesmo intervalo, a criação do bloqueio deve ser recusada.

O Administrador deve primeiro:

- resolver;
- recusar;
- ou cancelar

as reservas conflitantes.

Depois poderá criar o bloqueio.

Isso preserva histórico explícito das decisões.

---

# 21. Calendário e disponibilidade

A consulta de disponibilidade deve considerar:

- áreas ativas;
- reservas `PENDING`;
- reservas `APPROVED`;
- bloqueios.

Reservas:

```text
REJECTED
CANCELED
```

não ocupam disponibilidade.

O calendário não deve carregar todos os dados históricos do sistema quando apenas um intervalo for necessário.

---

# 22. Histórico de reservas

Reservas não devem ser excluídas apenas porque:

- foram recusadas;
- foram canceladas;
- já aconteceram.

O histórico deve permitir identificar pelo menos:

- área;
- intervalo;
- status;
- unidade/Morador conforme permissão;
- datas relevantes.

O histórico é somente leitura no contexto do M3-08.

---

# 23. Permissões de reservas

| Operação | Morador | Administrador | Porteiro |
|---|---:|---:|---:|
| Consultar áreas | Sim | Sim | Não |
| Consultar disponibilidade | Sim | Sim | Não |
| Solicitar reserva | Sim | Não | Não |
| Consultar próprias reservas | Sim | - | Não |
| Consultar reservas administrativas | Não | Sim | Não |
| Aprovar | Não | Sim | Não |
| Recusar | Não | Sim | Não |
| Cancelar própria reserva | Sim | - | Não |
| Cancelar administrativamente | Não | Sim | Não |
| Cadastrar/editar área | Não | Sim | Não |
| Ativar/inativar área | Não | Sim | Não |
| Criar bloqueios | Não | Sim | Não |

---

# 24. Notificações relacionadas a reservas

O fluxo de reservas deve utilizar notificações internas.

Eventos mínimos:

## Reserva aprovada

```text
APPROVED
→ notificar o Morador responsável
```

## Reserva recusada

```text
REJECTED
→ notificar o Morador responsável
```

## Reserva cancelada pelo Administrador

```text
CANCELED
→ notificar o Morador responsável
```

Quando o próprio Morador cancelar sua reserva, não é necessário enviar notificação para ele mesmo.

---

# 25. Encomendas

Uma encomenda representa um item destinado a uma unidade do condomínio.

O sistema suporta:

1. encomenda prevista previamente pelo Morador;
2. encomenda recebida sem previsão anterior.

Cadastrar previsão não é requisito para que a Portaria possa registrar um recebimento.

---

# 26. Estados de encomenda

Estados de negócio:

```text
EXPECTED
RECEIVED
WITHDRAWN
```

Os nomes concretos podem seguir o Enum existente no projeto.

---

# 27. EXPECTED

Representa uma encomenda que o Morador informou que espera receber.

Ainda não representa um recebimento físico na Portaria.

---

# 28. RECEIVED

Representa uma encomenda que foi efetivamente recebida pela Portaria.

O sistema deve registrar:

- horário do recebimento;
- Porteiro responsável;
- unidade destinatária.

---

# 29. WITHDRAWN

Representa uma encomenda retirada da Portaria.

O sistema deve registrar:

- horário da retirada;
- operador responsável pela confirmação.

É um estado terminal.

---

# 30. Transições de encomenda

Fluxo com previsão:

```text
EXPECTED
→ RECEIVED
→ WITHDRAWN
```

Fluxo sem previsão:

```text
RECEIVED
→ WITHDRAWN
```

Não permitir:

```text
EXPECTED → WITHDRAWN
```

Também não permitir:

```text
WITHDRAWN → RECEIVED
```

ou segunda retirada.

---

# 31. Cadastro de encomenda prevista

Somente Morador pode cadastrar previsão.

A unidade deve ser obtida através do usuário autenticado.

O frontend não controla a unidade efetiva da encomenda.

A previsão pode conter informações existentes no modelo, como:

- descrição;
- remetente;
- código de rastreio quando disponível.

Não é obrigatória integração com transportadoras.

---

# 32. Recebimento com previsão

Quando existir previsão correspondente, o Porteiro pode registrar seu recebimento.

Resultado:

```text
EXPECTED → RECEIVED
```

Devem ser registrados:

- `received_at`;
- Porteiro responsável;
- demais dados necessários existentes no modelo.

O operador deve vir da sessão.

---

# 33. Recebimento sem previsão

A ausência de previsão não pode impedir recebimento.

O Porteiro deve conseguir registrar uma nova encomenda diretamente como:

```text
RECEIVED
```

desde que consiga identificar a unidade destinatária.

Isso representa um recebimento real que não foi previamente cadastrado pelo Morador.

---

# 34. Associação com previsão existente

Quando uma previsão for utilizada no recebimento, ela deve representar o mesmo registro de encomenda.

Não criar:

```text
EXPECTED #1
```

e depois outro registro independente:

```text
RECEIVED #2
```

para o mesmo recebimento quando a previsão foi identificada.

A transição deve ocorrer sobre a previsão existente.

---

# 35. Registro de recebimento

Somente Porteiro pode registrar recebimento.

O sistema deve impedir:

- recebimento duplicado;
- alteração arbitrária de status pelo frontend;
- escolha de operador enviada pelo frontend.

---

# 36. Retirada

Somente encomendas:

```text
RECEIVED
```

podem ser retiradas.

A retirada deve ser confirmada pelo Porteiro.

Resultado:

```text
RECEIVED → WITHDRAWN
```

Registrar:

- horário da retirada;
- operador responsável.

Não permitir retirada duplicada.

---

# 37. Encomenda já retirada

Uma encomenda `WITHDRAWN` é histórica.

Ela não deve retornar para:

```text
RECEIVED
```

nem:

```text
EXPECTED
```

através dos fluxos normais.

---

# 38. Isolamento das encomendas

Morador só pode consultar encomendas da própria unidade.

Exemplo:

```text
Morador Unit A
→ não acessa encomendas Unit B
```

A unidade não pode ser alterada através de manipulação da requisição.

Porteiros possuem acesso operacional necessário para recebimento e retirada.

---

# 39. Histórico de encomendas

O histórico deve permitir consultar:

- encomenda;
- unidade;
- status;
- previsão, quando existente;
- recebimento;
- retirada.

O histórico deve preservar registros `WITHDRAWN`.

Encomendas não devem ser apagadas quando finalizadas.

---

# 40. Notificação de recebimento

Quando uma encomenda passar para:

```text
RECEIVED
```

o sistema deve notificar os usuários Moradores vinculados à unidade destinatária conforme a política atual do sistema.

Exemplo:

```text
Sua encomenda foi recebida pela Portaria.
```

Não incluir informações sensíveis desnecessárias na notificação.

---

# 41. Notificação de retirada

Uma confirmação interna de retirada pode ser gerada caso seja útil para o fluxo implementado.

Não é obrigatório enviar uma nova notificação ao próprio usuário apenas para repetir uma operação já confirmada presencialmente.

A funcionalidade mínima obrigatória é a notificação do recebimento.

---

# 42. Notificações internas

O M3-02 deve disponibilizar uma infraestrutura genérica de notificações internas.

Uma notificação deve pertencer a um usuário.

Deve possuir conceitualmente:

- tipo;
- título;
- mensagem;
- data;
- indicação lida/não lida;
- relacionamento com o usuário.

Pode possuir referência ao recurso relacionado se a arquitetura atual suportar isso.

---

# 43. Isolamento das notificações

Um usuário:

```text
só pode visualizar suas próprias notificações
```

Não pode:

- consultar notificações de outro usuário;
- marcar notificação de outro usuário como lida.

---

# 44. Estados da notificação

O estado pode ser representado por:

```text
read_at = null
→ não lida
```

```text
read_at preenchido
→ lida
```

Não é necessário criar workflow complexo.

---

# 45. Eventos mínimos de notificação

O contrato entre Reservas, Encomendas e Notificações será:

| Evento | Destinatário |
|---|---|
| Reserva aprovada | Morador responsável |
| Reserva recusada | Morador responsável |
| Reserva cancelada pelo Admin | Morador responsável |
| Encomenda recebida | Moradores da unidade |
| Encomenda retirada | Opcional |

M3-02 implementa a infraestrutura.

M3-09 integra os eventos de reservas.

M3-11/M3-12 integram os eventos de encomendas quando necessário.

---

# 46. Notificação não é fonte de verdade

O estado da entidade continua sendo a fonte de verdade.

Exemplo:

```text
Reservation.status = APPROVED
```

é a informação oficial.

A existência de uma notificação de aprovação não substitui o status da reserva.

O mesmo se aplica às encomendas.

---

# 47. Dados derivados da sessão

Campos operacionais não devem ser controlados diretamente pelo frontend quando puderem ser determinados pelo contexto autenticado.

Exemplos:

```text
reservation.resident_id
reservation.unit_id
order.unit_id quando criada pelo Morador
received_by / doorman_id
withdrawn_by / operador
```

Devem ser derivados no backend.

---

# 48. Estados controlados pelo backend

O frontend não deve enviar livremente:

```text
reservation.status
order.status
notification.read_at de outra notificação
received_at
withdrawn_at
approved_by
received_by
withdrawn_by
```

Transições devem acontecer através de operações específicas do domínio.

---

# 49. Idempotência

Operações importantes devem impedir repetição inválida.

Exemplos:

```text
aprovar duas vezes
→ recusado/controlado
```

```text
receber mesma encomenda duas vezes
→ recusado
```

```text
retirar duas vezes
→ recusado
```

```text
cancelar reserva já cancelada
→ recusado
```

---

# 50. Histórico e integridade

Mudanças de estado não devem excluir registros históricos.

Estados terminais precisam permanecer consultáveis.

Isso se aplica a:

- reservas recusadas;
- reservas canceladas;
- reservas realizadas;
- encomendas retiradas.

---

# 51. Soft delete

Caso Models utilizem SoftDeletes, sua utilização não deve permitir perda de histórico operacional.

Registros utilizados por histórico não devem desaparecer simplesmente por exclusão de um recurso relacionado.

A estratégia deve seguir o padrão existente no projeto.

---

# 52. Tratamento de erros

Violações de regra de negócio devem gerar respostas controladas.

Exemplos:

- horário indisponível;
- conflito de reserva;
- área inativa;
- reserva já aprovada;
- retirada duplicada;
- encomenda ainda não recebida;
- recurso de outra unidade.

Não retornar erros internos ou stack traces ao usuário.

---

# 53. Responsabilidade do backend

Toda regra relevante deve possuir validação backend.

Especialmente:

- permissões;
- isolamento;
- conflitos;
- estados;
- transições;
- bloqueios;
- unidade;
- operadores.

Validação frontend existe apenas para melhorar a experiência.

---

# 54. Fora do escopo

Não fazem parte do Módulo 3:

- pagamento por reserva;
- cobrança automática;
- multas;
- lista de espera;
- integração com PIX;
- integração com Correios;
- integração com Amazon/Mercado Livre;
- rastreamento externo;
- reconhecimento facial;
- assinatura digital para retirada;
- push notification;
- SMS;
- WhatsApp;
- email transacional;
- reserva recorrente;
- múltiplos condomínios.

Esses recursos podem ser considerados evoluções futuras.

---

# 55. Dependências entre cards

## Base compartilhada

```text
M3-01
Definição das regras e contratos
```

Depois:

```text
M3-02
Infraestrutura de notificações
```

e:

```text
M3-03
Gestão de áreas comuns
```

podem avançar em paralelo.

---

# 56. Sequência de Reservas

```text
M3-03
Áreas comuns
   ↓
M3-04
Disponibilidade
   ↓
M3-05
Solicitação + conflitos
   ↓
M3-06
Aprovação / recusa / cancelamento
   ↓
M3-07
Bloqueios
   ↓
M3-08
Histórico
   ↓
M3-09
Notificações
```

M3-06 e M3-07 podem compartilhar partes da lógica de disponibilidade.

---

# 57. Sequência de Encomendas

```text
M3-02
Notificações
   ↓
M3-10
Previsão
   ↓
M3-11
Recebimento
   ↓
M3-12
Retirada
   ↓
M3-13
Histórico/status
```

---

# 58. Encerramento do módulo

Após as duas trilhas:

```text
Reservas concluídas
+
Encomendas concluídas
+
Notificações integradas
```

executar:

```text
M3-14
Validação integrada e preparação da demonstração
```

---

# 59. Critérios gerais de aceite do Módulo 3

O módulo estará funcional quando:

## Reservas

- Admin consegue gerenciar áreas;
- Morador consulta disponibilidade;
- Morador solicita reserva;
- conflitos são bloqueados;
- Admin aprova ou recusa;
- reservas podem ser canceladas corretamente;
- bloqueios impedem novas reservas;
- histórico é consultável;
- Morador é notificado sobre decisões relevantes.

## Encomendas

- Morador registra previsão;
- Porteiro registra encomenda prevista;
- Porteiro registra encomenda sem previsão;
- recebimento gera notificação;
- Porteiro registra retirada;
- retirada duplicada é bloqueada;
- Morador consulta status e histórico da própria unidade.

## Segurança

- regras são aplicadas no backend;
- perfis não acessam operações indevidas;
- unidade não é confiada ao frontend quando derivável;
- dados permanecem isolados;
- estados não podem ser manipulados livremente.

---

# 60. Princípio de implementação

Os cards posteriores devem preservar o padrão arquitetural atual do CoSphere.

Conceitualmente:

```text
Route
→ Middleware / Policy
→ FormRequest
→ Controller
→ Service
→ Model / Database
```

Controllers devem permanecer enxutos.

Regras de negócio devem ficar centralizadas em Services ou estruturas equivalentes já utilizadas pelo projeto.

Não criar camadas ou abstrações adicionais sem necessidade concreta.