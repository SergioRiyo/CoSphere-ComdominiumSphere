# Módulo 3 — Regras de Negócio

## 1. Objetivo

O Módulo 3 do CoSphere contempla dois fluxos principais:

- **Reservas de áreas comuns**;
- **Controle de encomendas**.

O módulo também utiliza **notificações internas** para comunicar eventos relevantes aos usuários.

Este documento define as regras funcionais que devem ser respeitadas durante o desenvolvimento dos cards M3-02 a M3-14.

As regras de negócio devem ser garantidas no backend. Validações no frontend existem apenas para melhorar a experiência do usuário.

---

# 2. Perfis e responsabilidades

## Administrador

No fluxo de reservas, o Administrador pode:

- cadastrar e editar áreas comuns;
- ativar, inativar ou colocar áreas em manutenção;
- consultar reservas;
- aprovar ou recusar solicitações;
- cancelar reservas;
- criar bloqueios de disponibilidade;
- consultar histórico.

O Administrador não solicita reservas como Morador.

## Morador

O Morador pode:

- consultar áreas disponíveis;
- consultar calendário e disponibilidade;
- solicitar reservas;
- consultar suas reservas;
- cancelar suas próprias reservas quando permitido;
- cadastrar previsão de encomenda;
- consultar encomendas da própria unidade.

## Porteiro

O Porteiro atua somente no fluxo operacional de encomendas deste módulo.

Pode:

- registrar recebimento;
- identificar a unidade destinatária;
- confirmar retirada.

O Porteiro não participa da gestão de reservas.

---

# 3. Regras gerais de segurança

Todas as operações devem validar no backend:

- autenticação;
- usuário ativo;
- verificação de e-mail quando aplicável;
- papel correto;
- propriedade ou vínculo com o recurso.

Dados que podem ser obtidos pela sessão não devem ser confiados ao frontend.

Exemplos:

- Morador e unidade de uma reserva;
- unidade de uma encomenda cadastrada pelo Morador;
- Porteiro responsável pelo recebimento;
- Porteiro responsável pela retirada.

Um Morador nunca pode acessar recursos pertencentes a outra unidade.

---

# 4. Áreas comuns

Uma área comum representa um espaço do condomínio que pode receber reservas.

A implementação deve utilizar os campos já existentes na modelagem, incluindo quando disponíveis:

- nome;
- descrição;
- horário inicial;
- horário final;
- duração máxima;
- regras de utilização;
- necessidade de aprovação;
- status.

Não será adicionada capacidade máxima neste módulo, pois ela não é necessária para o fluxo principal do TCC.

---

## 4.1 Estados da área

As áreas podem utilizar:

- `active`;
- `inactive`;
- `maintenance`.

### Active

Permite novas solicitações, respeitando as demais regras de disponibilidade.

### Inactive

Não aceita novas reservas.

### Maintenance

Representa indisponibilidade temporária e também não aceita novas reservas.

Alterar uma área para `inactive` ou `maintenance` não cancela automaticamente reservas existentes.

Quando necessário, o Administrador deve cancelar explicitamente as reservas afetadas.

---

# 5. Reservas

Uma reserva representa a solicitação de uso de uma área comum durante determinado intervalo.

Estados:

- `PENDING`;
- `APPROVED`;
- `REJECTED`;
- `CANCELED`.

---

## 5.1 Transições

Fluxo quando a área exige aprovação:

```text
PENDING → APPROVED
PENDING → REJECTED
PENDING → CANCELED
APPROVED → CANCELED
```

Não são permitidas transições como:

```text
REJECTED → APPROVED
CANCELED → APPROVED
REJECTED → PENDING
```

Uma nova tentativa deve gerar uma nova solicitação.

---

# 6. Aprovação configurável

A necessidade de aprovação é definida pela configuração da área.

## Área com aprovação

Quando:

```text
requires_approval = true
```

uma solicitação válida inicia como:

```text
PENDING
```

e precisa ser analisada pelo Administrador.

## Área sem aprovação

Quando:

```text
requires_approval = false
```

uma solicitação válida pode iniciar diretamente como:

```text
APPROVED
```

A aprovação automática não ignora nenhuma regra de disponibilidade ou conflito.

A decisão é tomada pelo backend com base na configuração da área.

---

# 7. Solicitação de reserva

Somente Morador pode solicitar reserva.

Uma solicitação só pode ser criada quando:

- o Morador está ativo;
- possui unidade válida;
- a área existe;
- a área está `active`;
- início é anterior ao fim;
- início e fim pertencem ao mesmo dia;
- o horário respeita o funcionamento da área;
- a duração respeita o limite configurado;
- não existe reserva conflitante;
- não existe bloqueio conflitante.

Morador e unidade devem ser obtidos pelo backend.

---

# 8. Datas e horários

Toda reserva deve obedecer:

```text
start_at < end_at
```

Reservas não podem começar e terminar em dias diferentes.

Exemplo permitido:

```text
18:00 → 22:00
```

no mesmo dia.

Não permitido:

```text
22:00 → 02:00 do dia seguinte
```

Reservas completamente no passado não podem ser solicitadas.

Quando houver horário configurado para a área:

```text
opening_time <= start_at
end_at <= closing_time
```

O frontend deve preservar o horário local informado pelo usuário.

---

# 9. Duração máxima

Quando uma área possuir limite de duração:

```text
duração da reserva <= duração máxima da área
```

A validação deve ocorrer no backend.

Solicitações acima desse limite são recusadas.

---

# 10. Conflitos de reserva

Duas reservas da mesma área entram em conflito quando:

```text
novo_inicio < existente_fim
AND
novo_fim > existente_inicio
```

Exemplo:

```text
Existente: 14:00 → 16:00
Nova:      15:00 → 17:00
```

Existe conflito.

Intervalos adjacentes são permitidos:

```text
14:00 → 16:00
16:00 → 18:00
```

---

## 10.1 Estados que ocupam disponibilidade

Bloqueiam novos horários:

- `PENDING`;
- `APPROVED`.

Não bloqueiam:

- `REJECTED`;
- `CANCELED`.

Assim, duas solicitações concorrentes não podem permanecer pendentes para o mesmo intervalo.

---

# 11. Concorrência

A ausência de conflito deve ser garantida no backend.

O frontend e o calendário não são suficientes para proteger disponibilidade.

A implementação deve impedir que duas requisições simultâneas consigam reservar o mesmo intervalo da mesma área.

A estratégia técnica será definida nos cards responsáveis pela implementação.

---

# 12. Aprovação e recusa

Somente Administrador pode aprovar ou recusar.

Apenas reservas `PENDING` podem ser analisadas.

Antes de aprovar, o sistema deve revalidar:

- status da área;
- disponibilidade;
- bloqueios;
- conflitos;
- horário.

Uma aprovação bem-sucedida resulta em:

```text
PENDING → APPROVED
```

Uma recusa resulta em:

```text
PENDING → REJECTED
```

---

# 13. Cancelamento

O Morador pode cancelar sua própria reserva enquanto ela estiver:

- `PENDING`;
- `APPROVED`;

desde que o período da reserva ainda não tenha iniciado.

O Administrador também pode cancelar reservas quando necessário.

Resultado:

```text
PENDING → CANCELED
```

ou:

```text
APPROVED → CANCELED
```

Reservas recusadas ou já canceladas não podem ser canceladas novamente.

---

# 14. Reservas concluídas

Não será criado um estado persistido `COMPLETED`.

Uma reserva:

```text
status = APPROVED
end_at < agora
```

pode ser apresentada na interface como concluída, mantendo `APPROVED` no banco.

---

# 15. Alteração das regras da área

Alterar:

- horário;
- duração;
- regras;
- aprovação obrigatória;
- status;

não modifica automaticamente reservas existentes.

As novas configurações são aplicadas às novas solicitações.

Qualquer alteração de uma reserva já existente deve ocorrer por ação explícita do fluxo de reservas.

---

# 16. Bloqueios de disponibilidade

O Administrador pode bloquear uma área durante determinado período.

Um bloqueio representa:

- área;
- início;
- fim;
- motivo;
- responsável.

Durante o bloqueio, novas reservas no intervalo são proibidas.

A mesma regra de sobreposição utilizada em reservas deve ser aplicada aos bloqueios.

---

## 16.1 Bloqueio contra reserva existente

Um bloqueio não pode ser criado sobre uma reserva `PENDING` ou `APPROVED`.

O Administrador deve primeiro resolver a reserva conflitante.

Isso evita alterações silenciosas no histórico.

---

# 17. Disponibilidade e calendário

A disponibilidade deve considerar:

- status da área;
- horário de funcionamento;
- duração máxima;
- reservas `PENDING`;
- reservas `APPROVED`;
- bloqueios.

Reservas `REJECTED` e `CANCELED` não ocupam horário.

O calendário apresenta a disponibilidade, mas a confirmação final sempre ocorre no backend.

---

# 18. Histórico de reservas

Reservas não devem ser apagadas quando:

- recusadas;
- canceladas;
- finalizadas temporalmente.

O histórico deve permitir identificar:

- área;
- período;
- status;
- Morador/unidade quando autorizado;
- datas relevantes.

O histórico é somente leitura.

---

# 19. Permissões de reservas

| Operação | Morador | Admin | Porteiro |
|---|:---:|:---:|:---:|
| Consultar áreas | ✅ | ✅ | ❌ |
| Consultar disponibilidade | ✅ | ✅ | ❌ |
| Solicitar reserva | ✅ | ❌ | ❌ |
| Consultar próprias reservas | ✅ | — | ❌ |
| Consultar reservas administrativas | ❌ | ✅ | ❌ |
| Aprovar | ❌ | ✅ | ❌ |
| Recusar | ❌ | ✅ | ❌ |
| Cancelar própria reserva | ✅ | — | ❌ |
| Cancelar administrativamente | ❌ | ✅ | ❌ |
| Gerenciar áreas | ❌ | ✅ | ❌ |
| Criar bloqueios | ❌ | ✅ | ❌ |

---

# 20. Notificações de reservas

Eventos mínimos:

### Reserva aprovada

Notificar o Morador responsável.

### Reserva recusada

Notificar o Morador responsável.

### Reserva cancelada pelo Administrador

Notificar o Morador responsável.

Quando o próprio Morador cancelar sua reserva, não é necessário notificá-lo sobre sua própria ação.

O M3-02 fornece a infraestrutura de notificações.

O M3-09 integra essa infraestrutura ao fluxo de reservas.

---

# 21. Encomendas

Uma encomenda representa um item destinado a uma unidade.

O sistema suporta:

1. encomenda prevista pelo Morador;
2. encomenda recebida sem previsão.

A previsão não é obrigatória para que a Portaria possa receber uma encomenda.

---

# 22. Estados de encomenda

Estados:

- `EXPECTED`;
- `RECEIVED`;
- `WITHDRAWN`.

---

## 22.1 Fluxo previsto

```text
EXPECTED → RECEIVED → WITHDRAWN
```

## 22.2 Fluxo sem previsão

```text
RECEIVED → WITHDRAWN
```

Não permitir:

```text
EXPECTED → WITHDRAWN
WITHDRAWN → RECEIVED
```

Uma retirada já confirmada não pode ser registrada novamente.

---

# 23. Encomenda prevista

Somente Morador pode registrar previsão.

A unidade deve ser derivada do usuário autenticado.

A previsão pode incluir informações existentes na modelagem, como:

- descrição;
- remetente;
- código de rastreio.

Integrações com transportadoras não fazem parte do escopo.

---

# 24. Recebimento

Somente Porteiro pode registrar o recebimento físico.

Quando existir previsão correspondente:

```text
EXPECTED → RECEIVED
```

O mesmo registro deve ser atualizado.

Não criar um segundo registro apenas para representar o recebimento.

Ao receber, registrar:

- data/hora;
- Porteiro responsável;
- unidade destinatária.

O Porteiro responsável deve ser derivado da sessão.

---

# 25. Recebimento sem previsão

A Portaria pode receber encomenda sem cadastro anterior.

Nesse caso, ela é criada diretamente como:

```text
RECEIVED
```

desde que a unidade destinatária seja identificada.

---

# 26. Retirada

Somente encomendas `RECEIVED` podem ser retiradas.

Resultado:

```text
RECEIVED → WITHDRAWN
```

Registrar:

- data/hora da retirada;
- operador responsável.

Retirada duplicada deve ser bloqueada.

---

# 27. Isolamento de encomendas

Moradores só podem consultar encomendas de sua própria unidade.

Um Morador da Unidade A não pode consultar dados da Unidade B.

O Porteiro possui acesso operacional necessário para registrar recebimento e retirada.

IDs de unidade enviados pelo Morador não substituem o vínculo determinado pela sessão.

---

# 28. Histórico de encomendas

O histórico deve preservar encomendas:

- previstas;
- recebidas;
- retiradas.

Encomendas finalizadas não devem ser excluídas.

A consulta deve mostrar apenas dados permitidos ao perfil autenticado.

---

# 29. Notificação de encomendas

Quando uma encomenda passar para:

```text
RECEIVED
```

o sistema deve notificar os Moradores da unidade destinatária conforme a política definida pelo módulo.

Exemplo:

```text
Sua encomenda foi recebida pela Portaria.
```

A notificação de retirada é opcional.

---

# 30. Notificações internas

O sistema de notificações deve permitir:

- listar notificações do usuário;
- identificar não lidas;
- marcar como lida;
- impedir acesso a notificações de outros usuários.

Conceitualmente:

```text
read_at = null
→ não lida

read_at preenchido
→ lida
```

A notificação pertence a um usuário.

---

# 31. Contrato de notificações

Eventos mínimos do Módulo 3:

| Evento | Destinatário |
|---|---|
| Reserva aprovada | Morador responsável |
| Reserva recusada | Morador responsável |
| Reserva cancelada pelo Admin | Morador responsável |
| Encomenda recebida | Moradores da unidade |
| Encomenda retirada | Opcional |

A notificação não é a fonte de verdade do estado.

O estado da `Reservation` ou da encomenda continua sendo a informação oficial.

---

# 32. Idempotência

Operações devem impedir repetições inválidas.

Exemplos:

```text
aprovar reserva já aprovada
→ recusado
```

```text
cancelar reserva já cancelada
→ recusado
```

```text
registrar recebimento duas vezes
→ recusado
```

```text
registrar retirada duas vezes
→ recusado
```

---

# 33. Histórico e integridade

Mudanças de estado não devem apagar registros históricos.

Isso se aplica a:

- reservas aprovadas;
- reservas recusadas;
- reservas canceladas;
- encomendas recebidas;
- encomendas retiradas.

Quando houver SoftDeletes, eles não devem ser utilizados de forma que quebrem consultas históricas necessárias.

---

# 34. Tratamento de erros

Violações das regras devem produzir respostas controladas.

Exemplos:

- área indisponível;
- horário inválido;
- conflito;
- bloqueio;
- reserva em estado incompatível;
- encomenda já recebida;
- retirada duplicada;
- recurso de outra unidade.

Não expor erros internos ou stack traces.

---

# 35. Fora do escopo

Não fazem parte do Módulo 3:

- pagamento por reserva;
- cobrança e multas;
- PIX;
- fila de espera;
- reservas recorrentes;
- reservas atravessando dias;
- integração com Correios;
- rastreamento externo;
- integração com marketplaces;
- SMS;
- WhatsApp;
- push notification;
- assinatura digital;
- reconhecimento facial;
- múltiplos condomínios.

Esses recursos podem ser considerados evoluções futuras.

---

# 36. Dependências dos cards

## Base compartilhada

```text
M3-01 — Regras e contratos
```

Após o M3-01:

```text
M3-02 — Notificações
```

e:

```text
M3-03 — Áreas comuns
```

podem ser desenvolvidos em paralelo.

---

## Trilha de Reservas — SM

```text
M3-03 — Gestão de áreas
   ↓
M3-04 — Calendário e disponibilidade
   ↓
M3-05 — Solicitação e conflitos
   ↓
M3-06 — Aprovação, recusa e cancelamento
   ↓
M3-07 — Bloqueios
   ↓
M3-08 — Histórico
   ↓
M3-09 — Notificações
```

---

## Trilha de Encomendas — YF

```text
M3-02 — Notificações internas
   ↓
M3-10 — Encomenda prevista
   ↓
M3-11 — Recebimento
   ↓
M3-12 — Retirada
   ↓
M3-13 — Status e histórico
```

---

# 37. Validação final

Após conclusão das duas trilhas:

```text
M3-14 — Validar módulo e preparar demonstração
```

Deve comprovar o funcionamento integrado de:

- áreas comuns;
- reservas;
- conflitos;
- bloqueios;
- notificações;
- encomendas;
- recebimento;
- retirada;
- isolamento por perfil/unidade.

---

# 38. Critério geral de conclusão

O Módulo 3 estará concluído quando:

## Reservas

- Admin gerencia áreas;
- Morador consulta disponibilidade;
- Morador solicita reserva;
- conflitos são impedidos;
- aprovação automática ou administrativa respeita a configuração da área;
- Admin aprova, recusa e cancela;
- bloqueios funcionam;
- histórico funciona;
- notificações são entregues nos eventos definidos.

## Encomendas

- Morador registra previsão;
- Porteiro recebe encomenda prevista;
- Porteiro recebe encomenda não prevista;
- Morador é notificado;
- retirada é registrada;
- retirada duplicada é impedida;
- histórico e status podem ser consultados.

## Segurança

- backend aplica todas as regras;
- perfis possuem apenas as permissões necessárias;
- Morador não acessa outra unidade;
- IDs derivados da sessão não são confiados ao frontend;
- estados só mudam através das operações previstas no domínio.