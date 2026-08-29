# Modulo 2 - Regras de negocio de visitantes e controle de acesso

Este documento define as regras de negocio do Modulo 2 do CoSphere. Ele e a
fonte de verdade para a implementacao do cadastro de visitantes, autorizacoes,
convites externos, QR Code, validacao na portaria, entrada, saida e historico.

## Objetivo

Permitir que um morador autorize uma visita por cadastro direto ou por convite
externo. Depois da conclusao do cadastro, o sistema gera um QR Code que o
visitante apresenta na portaria. O porteiro autenticado le o codigo pelo celular,
confere os dados retornados pelo CoSphere e decide se registra a entrada.

O modulo nao se integra a catracas, portoes ou outros equipamentos fisicos. A
liberacao fisica continua sendo responsabilidade do porteiro; o CoSphere valida
a autorizacao e registra a operacao.

## Atores

- **Morador:** cria, acompanha e cancela autorizacoes vinculadas a sua unidade.
- **Visitante:** preenche os dados pelo convite externo e apresenta o QR Code.
- **Porteiro:** valida o QR Code, confere os dados e registra entrada e saida.

## Decisoes adotadas

- Cada autorizacao permite uma entrada e uma saida.
- Nao ha reentrada com a mesma autorizacao.
- A autorizacao passa para `used` somente depois do registro da saida.
- O QR Code contem apenas um token opaco e nao inclui dados pessoais.
- O convite externo expira e pode ser utilizado apenas uma vez.
- Autorizacoes de moradores inativos ou unidades inativas nao sao aceitas.
- Porteiros diferentes podem registrar a entrada e a saida.
- O historico nao pode ser alterado ou apagado pelos usuarios do modulo.
- A leitura do QR Code sera realizada pela camera do celular do porteiro.
- A digitacao manual do codigo sera mantida como alternativa a camera.

## Fluxo geral

```text
Morador autoriza o visitante
        ↓
Cadastro direto ou convite externo
        ↓
Cadastro do visitante concluido
        ↓
Autorizacao ativa e QR Code gerado
        ↓
Visitante apresenta o QR Code
        ↓
Porteiro le o QR Code pelo celular
        ↓
CoSphere valida a autorizacao
        ↓
Porteiro confere os dados
        ↓
Entrada registrada
        ↓
Saida registrada
        ↓
Autorizacao utilizada e historico preservado
```

## Cadastro direto pelo morador

O morador informa:

- nome completo do visitante;
- CPF;
- telefone;
- placa do veiculo, quando houver;
- data e hora inicial da autorizacao;
- data e hora final da autorizacao.

O sistema deve derivar do usuario autenticado, sem aceitar esses valores do
formulario:

- o morador responsavel;
- a unidade vinculada;
- a data de criacao da autorizacao.

Depois da validacao dos dados, o sistema cria ou localiza o visitante pelo CPF,
cria uma autorizacao `active` e gera o token usado no QR Code. O morador pode
baixar ou compartilhar o QR Code com o visitante por um canal externo, como um
aplicativo de mensagens.

O CoSphere nao precisa enviar mensagens pelo WhatsApp neste modulo.

## Cadastro por convite externo

O morador define o periodo da visita e solicita um convite. O sistema cria uma
autorizacao `pending_data` vinculada ao morador e a sua unidade.

O convite deve:

- usar um token aleatorio de alta entropia;
- armazenar somente o hash do token no banco;
- possuir prazo de expiracao;
- ser utilizado uma unica vez;
- poder ser revogado pelo morador antes do preenchimento;
- deixar de funcionar quando a visita atingir seu horario inicial;
- ter protecao contra tentativas excessivas.

Como regra inicial, o convite expira em 24 horas ou no inicio da visita, o que
ocorrer primeiro.

Pelo convite, o visitante informa:

- nome completo;
- CPF;
- telefone;
- placa do veiculo, quando houver;
- confirmacao de que os dados estao corretos.

O visitante nao pode alterar o morador, a unidade ou o periodo da visita. Depois
do preenchimento valido, o sistema:

1. cadastra ou localiza o visitante;
2. vincula o visitante a autorizacao;
3. altera o status de `pending_data` para `active`;
4. marca o convite como utilizado;
5. gera o QR Code;
6. permite que o visitante visualize e baixe o QR Code.

Um convite expirado, revogado ou utilizado nao pode exibir o formulario nem
revelar dados da autorizacao.

## Periodo de validade

Toda autorizacao possui data e hora inicial e final.

- Antes do inicio, a autorizacao e futura e nao permite entrada.
- Durante o periodo, uma autorizacao `active` pode ser utilizada.
- Depois do horario final, a autorizacao e considerada `expired`.
- A data final deve ser posterior a data inicial.
- O sistema nao aceita a criacao de autorizacoes integralmente no passado.

Datas recebidas e exibidas devem considerar o timezone definido para o
condominio. A persistencia deve seguir a convencao de timezone adotada pela
aplicacao, com conversao consistente na entrada e na exibicao.

## QR Code e codigo manual

O QR Code nao armazena nome, CPF, telefone, placa, unidade ou periodo da visita.
Ele contem somente um token opaco semelhante a:

```text
csa_7f913cb343e94836a291b8a
```

O token nao possui significado fora do CoSphere. Depois da leitura, o navegador
envia o token ao backend, que localiza e valida a autorizacao.

O mesmo identificador pode ser informado manualmente quando a camera estiver
indisponivel. QR Code e codigo manual seguem exatamente as mesmas regras de
validacao.

Tokens completos nao devem ser registrados em logs, mensagens de erro ou
historicos de auditoria.

## Leitura pelo celular do porteiro

O porteiro acessa o CoSphere pelo navegador do celular e abre a pagina de
controle de acesso. A pagina oferece:

- leitura do QR Code pela camera;
- digitacao manual do codigo;
- lista de visitantes que estao dentro do condominio;
- consulta do historico de acessos.

Ao iniciar a leitura, o navegador solicita permissao para acessar a camera. A
negacao dessa permissao nao pode bloquear o uso do codigo manual.

Em ambiente publicado, o acesso a camera pelo navegador requer HTTPS. O leitor
deve impedir que a mesma imagem dispare varias validacoes simultaneas.

## Validacao da autorizacao

Somente um usuario autenticado, ativo e com papel de porteiro pode validar uma
autorizacao e registrar acessos. O backend deriva o porteiro da sessao e nunca
aceita um `doorman_id` enviado pelo navegador.

Para liberar a operacao, o sistema verifica:

- existencia do token;
- status da autorizacao;
- inicio e fim do periodo autorizado;
- conclusao dos dados do visitante;
- situacao ativa do morador;
- situacao ativa da unidade;
- inexistencia de outra entrada aberta para a autorizacao;
- inexistencia de uso anterior concluido.

Quando a autorizacao for valida, a tela pode apresentar ao porteiro:

- nome do visitante;
- CPF mascarado ou somente os ultimos digitos;
- placa do veiculo;
- unidade de destino;
- nome do morador responsavel;
- data e horario autorizado;
- status da autorizacao.

O porteiro pode solicitar um documento para conferir a identidade do visitante.

Ler o QR Code nao registra automaticamente a entrada. A validacao e a entrada
sao operacoes separadas para que o porteiro possa conferir os dados antes de
liberar fisicamente o acesso.

## Acesso negado

O acesso e negado quando ocorrer, entre outros, um destes casos:

- codigo inexistente ou invalido;
- autorizacao ainda nao iniciada;
- autorizacao expirada;
- autorizacao cancelada;
- autorizacao ja utilizada;
- cadastro externo ainda nao concluido;
- morador inativo;
- unidade inativa;
- visitante com entrada ja aberta.

O resultado deve apresentar um motivo claro ao porteiro sem revelar dados
pessoais de uma autorizacao invalida. Codigos desconhecidos devem produzir uma
resposta controlada e nunca um erro interno da aplicacao.

As tentativas negadas conhecidas podem ser preservadas no historico com data,
porteiro e motivo da recusa. Codigos desconhecidos devem ser registrados, quando
necessario, sem armazenar o token completo.

## Registro de entrada

Depois de uma validacao bem-sucedida e da conferencia dos dados, o porteiro
seleciona **Registrar entrada**.

O sistema registra:

- autorizacao utilizada;
- visitante;
- data e hora da entrada;
- porteiro responsavel pela entrada;
- unidade visitada;
- status do acesso.

A operacao deve ser transacional e impedir duas entradas abertas para a mesma
autorizacao, inclusive em cliques repetidos ou requisicoes concorrentes.

Enquanto houver uma entrada sem saida, uma nova leitura deve informar que o
visitante ja esta no condominio e oferecer a operacao apropriada de saida.

## Registro de saida

A saida pode ser iniciada pela leitura do mesmo QR Code ou pela lista de
visitantes presentes. A lista e necessaria para os casos em que o visitante nao
possui mais o QR Code.

O sistema registra:

- data e hora da saida;
- porteiro responsavel pela saida;
- encerramento do acesso;
- alteracao da autorizacao para `used`.

O porteiro da saida pode ser diferente do porteiro da entrada. Os dois devem ser
preservados no historico.

Nao e permitido registrar saida sem entrada aberta nem registrar a mesma saida
duas vezes. Depois da saida, o QR Code nao pode ser reutilizado. Uma nova visita
exige uma nova autorizacao.

## Cancelamento

O morador pode cancelar uma autorizacao `pending_data` ou `active` enquanto nao
existir uma entrada registrada.

O cancelamento:

- altera o status para `canceled`;
- invalida imediatamente o QR Code;
- revoga o convite externo ainda ativo;
- preserva a autorizacao no historico.

Uma autorizacao com entrada aberta nao pode ser cancelada. Nesse caso, a
portaria deve registrar a saida.

## Estados da autorizacao

| Status | Significado | Permite QR valido | Permite entrada |
| --- | --- | --- | --- |
| `pending_data` | Visitante ainda nao concluiu o cadastro externo | Nao | Nao |
| `active` | Cadastro concluido e autorizacao disponivel no periodo definido | Sim | Sim, dentro do periodo |
| `expired` | Periodo autorizado encerrado | Nao | Nao |
| `canceled` | Autorizacao cancelada pelo morador | Nao | Nao |
| `used` | Entrada e saida concluidas | Nao | Nao |

Transicoes principais:

```text
pending_data → active → used
pending_data → canceled
pending_data → expired
active       → canceled
active       → expired
```

## Historico

O historico e permanente para os usuarios do modulo e nao pode ser editado ou
excluido pelas interfaces de morador ou portaria.

### Historico do morador

O morador visualiza somente autorizacoes vinculadas a sua unidade, incluindo:

- visitante;
- periodo autorizado;
- entrada e saida;
- status da autorizacao;
- QR Code enquanto ainda for valido.

### Historico da portaria

O porteiro pode consultar:

- entradas;
- saidas;
- visitantes atualmente presentes;
- acessos negados previstos na modelagem;
- motivos das recusas;
- porteiro responsavel pela entrada;
- porteiro responsavel pela saida.

## Autorizacao e isolamento

- O morador acessa somente autorizacoes vinculadas a sua unidade.
- O morador e a unidade sempre sao derivados do usuario autenticado.
- Somente o porteiro pode validar codigos e registrar entrada ou saida.
- O visitante acessa somente o convite externo que recebeu.
- Rotas publicas de convite nao compartilham as permissoes das rotas autenticadas.
- Policies, Form Requests, middleware de papel e filtros de ownership sao
  obrigatorios.
- A RLS nao substitui as verificacoes da camada Laravel.

## Dados pessoais e seguranca

O modulo processa CPF, telefone, placa e historico de presenca. Por isso:

- o QR Code nao contem dados pessoais;
- respostas de acesso negado nao revelam dados do visitante;
- CPF deve ser mascarado quando a exibicao completa nao for necessaria;
- tokens devem ser aleatorios, revogaveis e protegidos contra enumeracao;
- endpoints de convite e validacao devem possuir rate limiting;
- IDs de morador, unidade e porteiro enviados pelo cliente nao sao confiaveis;
- operacoes de entrada e saida devem preservar a auditoria dos responsaveis.

## Criterios de aceite das regras

- Cadastro direto e convite externo produzem uma autorizacao ativa com QR Code.
- O QR Code e lido pela camera do celular do porteiro.
- O codigo manual funciona como alternativa a camera.
- A leitura consulta o backend antes de exibir dados do visitante.
- O porteiro confirma a entrada depois de conferir os dados.
- Apenas uma entrada e uma saida sao permitidas por autorizacao.
- Porteiros diferentes podem registrar entrada e saida.
- A autorizacao se torna `used` depois da saida.
- QR Code cancelado, expirado, pendente ou utilizado nao libera acesso.
- Morador e unidade inativos invalidam a autorizacao.
- O historico permanece disponivel e nao pode ser apagado pelos usuarios.
