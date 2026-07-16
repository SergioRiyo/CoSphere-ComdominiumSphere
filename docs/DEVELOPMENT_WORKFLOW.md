# Desenvolvimento do Projeto

Este documento representa o guia oficial de desenvolvimento do projeto CoSphere, desenvolvido no contexto de um TCC de Engenharia de Software. Ele define a arquitetura, o fluxo de trabalho, as convenções de branch e commit, e as boas práticas que devem ser seguidas pela equipe durante todo o desenvolvimento.

## Contexto do Projeto

- Projeto desenvolvido por duas pessoas.
- Stack principal: Laravel 12 + React + Inertia + PostgreSQL (Supabase) + Docker.
- O sistema representa um único condomínio, portanto a entidade principal do domínio é Unit.
- Não existem entidades Block nem Condominium.
- O banco oficial de desenvolvimento é PostgreSQL/Supabase.
- SQLite é utilizado apenas para testes automatizados.
- O projeto já concluiu o Módulo 0 (Preparação do Ambiente) e agora iniciará a implementação dos módulos funcionais.

## Arquitetura Adotada

A arquitetura utilizada segue o fluxo abaixo:

```text
Controller
    ↓
Service
    ↓
Repository
    ↓
Model
```

### Princípios de Desenvolvimento

- Manter a separação de responsabilidades entre as camadas.
- Implementar regras de negócio exclusivamente no Service.
- Criar Repository apenas quando houver um caso real de uso.
- Evitar estruturas vazias ou abstrações desnecessárias.
- Manter o código simples, legível e consistente.
- Priorizar a implementação de funcionalidades previstas para o módulo atual.
- Documentar decisões relevantes quando houver impacto na arquitetura ou no fluxo de trabalho.

## Estrutura de Branches

O fluxo oficial de Git segue a estrutura abaixo:

```text
main
│
├── develop
│
├── module/01-usuarios
│      ├── feature/login-yasmin
│      ├── feature/crud-usuarios
│      └── ...
│
├── module/02-visitantes
│
├── module/03-reservas
│
└── ...
```

### Responsabilidade de cada branch

| Branch | Responsabilidade |
| --- | --- |
| main | Contém as versões estáveis e entregas importantes do projeto. |
| develop | Linha principal de integração dos módulos em desenvolvimento. |
| module/* | Branch específica de um módulo funcional, usada para concentrar o trabalho daquele escopo. |
| feature/* | Branch individual de desenvolvimento para uma tarefa ou funcionalidade específica. |

## Fluxo de Desenvolvimento

O fluxo abaixo deve ser seguido em todas as entregas do projeto:

1. Criar a branch do módulo a partir de develop.
2. Cada integrante cria sua própria feature a partir da branch do módulo.
3. Desenvolver apenas na própria feature.
4. Abrir Pull Request da feature para a branch do módulo.
5. Após todas as funcionalidades do módulo estarem concluídas e revisadas, realizar o merge da branch do módulo para develop.
6. Quando solicitado pela faculdade, utilizar a branch do módulo como referência para a entrega ou criar uma tag correspondente.
7. Após a validação do módulo, manter develop atualizada e utilizar main apenas para versões estáveis e entregas importantes.

### Ciclo de Vida de um Módulo

```text
1. Criar a branch do módulo

module/02-visitantes

↓

2. Cada integrante cria sua própria feature

feature/cadastro-visitante
feature/controle-acesso

↓

3. Desenvolvimento

↓

4. Pull Request para a branch do módulo

↓

5. Revisão entre a equipe

↓

6. Merge na branch do módulo

↓

7. Testes finais do módulo

↓

8. Merge para develop

↓

9. Quando solicitado pela faculdade:
- criar uma Tag da entrega
ou
- utilizar a própria branch do módulo

↓

10. Iniciar o próximo módulo
```

Essa estratégia organiza o desenvolvimento por módulos, mantém cada escopo isolado e facilita entregas parciais ao longo do TCC.

### Fluxo para Correções (Fixes)

```text
develop

↓

module/03-reservas

↓

feature/nova-funcionalidade

↓

merge na branch do módulo

↓

bug encontrado

↓

fix/correcao-reserva

↓

merge novamente na branch do módulo

↓

merge para develop
```

Enquanto o módulo ainda não foi concluído, as correções devem acontecer na própria branch do módulo. Isso evita que problemas pontuais afetem o trabalho de outros módulos e mantém o histórico mais limpo.

## Estratégia de Branches

O projeto utiliza uma branch dedicada para cada módulo porque isso permite:

- desenvolver módulos antecipadamente durante as férias;
- manter cada módulo isolado;
- corrigir bugs apenas naquele módulo;
- facilitar revisões;
- entregar exatamente o módulo solicitado pela faculdade;
- manter um histórico limpo e organizado.

## Acompanhamento dos Módulos

| Módulo | Branch | Status |
| --- | --- | --- |
| Módulo 0 | develop | 🟢 Concluído |
| Módulo 1 | module/01-usuarios | ⏳ |
| Módulo 2 | module/02-visitantes | ⏳ |
| Módulo 3 | module/03-reservas | ⏳ |
| Módulo 4 | module/04-encomendas | ⏳ |
| Módulo 5 | module/05-ocorrencias | ⏳ |
| Módulo 6 | module/06-comunicados | ⏳ |
| Módulo 7 | module/07-financeiro | ⏳ |

Legenda:
- 🟡 Em desenvolvimento
- 🟢 Concluído
- 🔵 Entregue
- 🔴 Bloqueado

Essa tabela deve ser atualizada conforme o andamento do projeto.

## Fluxo Oficial de Trabalho

```text
develop
      │
      ▼
criar module/03-reservas
      │
      ├───────────────┐
      ▼               ▼
feature/dev1      feature/dev2
      │               │
      ▼               ▼
Pull Request      Pull Request
      │               │
      └──────┬────────┘
             ▼
module/03-reservas
             │
      Testes finais
             │
             ▼
merge → develop
             │
quando solicitado pela faculdade
             │
             ▼
Tag da entrega
```

Esse é o fluxo oficial que deverá ser seguido durante todo o desenvolvimento do TCC.

## Responsabilidades da Equipe

Como o projeto é desenvolvido por duas pessoas, as responsabilidades devem ser claras e organizadas:

- Cada integrante desenvolve apenas na sua própria feature.
- Nenhum integrante deve trabalhar diretamente na branch do módulo.
- Toda alteração deve passar por Pull Request.
- O outro integrante deve revisar o código antes do merge.
- Sempre sincronizar a feature com a branch do módulo antes de abrir um PR.
- Resolver conflitos antes da aprovação.
- Manter comunicação sobre mudanças estruturais que afetem ambos.

## Convenção para Nomes de Branches

As branches devem seguir uma nomenclatura consistente e descritiva.

### Branches de módulo

```text
module/01-usuarios
module/02-visitantes
module/03-reservas
```

### Branches de funcionalidade

```text
feature/login
feature/crud-usuarios
feature/cadastro-visitante
feature/qrcode-acesso
feature/notificacoes
```

### Branches de correção

```text
fix/correcao-login
hotfix/erro-seeder
```

## Convenção de Nomes

A nomenclatura das branches deve seguir o padrão oficial adotado pelo projeto. Cada nome deve indicar claramente o tipo de trabalho e o escopo, utilizando prefixos como module, feature, fix e hotfix.

Exemplos:

```text
module/01-usuarios

module/02-visitantes

module/03-reservas

feature/login

feature/crud-usuarios

feature/cadastro-visitante

feature/qrcode-acesso

feature/notificacoes

fix/login

fix/reservation

hotfix/seeder
```

Todos os integrantes da equipe devem seguir essa convenção para manter consistência e facilitar a organização do histórico do Git.

## Convenção de Commits

Os commits devem seguir o padrão Conventional Commits.

| Tipo | Quando usar | Exemplo |
| --- | --- | --- |
| feat | Nova funcionalidade | feat(users): create user registration |
| fix | Correção de bug | fix(visitor): validate CPF |
| refactor | Melhoria estrutural sem mudar comportamento | refactor(reservation): simplify reservation service |
| test | Adição ou ajuste de testes | test(unit): add reservation tests |
| docs | Alteração de documentação | docs(workflow): update development workflow |
| chore | Tarefas de manutenção, configuração ou setup | chore(docker): update compose configuration |

### Regras mínimas para commits

- Manter commits pequenos e objetivos.
- Escrever mensagens claras e em português ou inglês de forma consistente.
- Evitar commits com múltiplas alterações sem contexto.
- Não misturar correções, refatorações e novas funcionalidades em um mesmo commit, quando isso prejudicar a leitura.

## Pull Requests

Todos os trabalhos devem ser integrados ao projeto por meio de Pull Requests.

### Boas práticas

- Nunca realizar merge diretamente em develop ou main.
- Sempre utilizar Pull Request.
- O outro integrante deve revisar antes do merge.
- Resolver conflitos antes da aprovação.
- Garantir que a branch esteja atualizada antes de abrir o PR.
- Verificar se testes e build estão passando antes de solicitar revisão.

## Template de Pull Request

Todo Pull Request deve seguir este modelo padrão:

```markdown
## Descrição

Explique resumidamente o que foi desenvolvido.

## Checklist

- [ ] Código revisado
- [ ] Testes executados
- [ ] Build funcionando
- [ ] Sem conflitos
- [ ] Documentação atualizada (quando necessário)
```

Esse template deve ser utilizado para garantir consistência, clareza e revisão adequada das mudanças.

## Definition of Done

Uma funcionalidade pode ser considerada concluída apenas quando todos os critérios abaixo forem atendidos:

- código implementado;
- regra de negócio funcionando;
- validações concluídas;
- testes passando;
- build executando corretamente;
- migrations funcionando;
- documentação atualizada quando necessário;
- Pull Request aprovado.

Somente após atender todos esses critérios uma funcionalidade pode ser considerada pronta para merge.

## Padrão Arquitetural

A implementação deve seguir o padrão abaixo para manter a organização do código.

### Controller

- Recebe a requisição.
- Chama o Service.
- Retorna a resposta.

### Service

- Contém toda a regra de negócio.
- Centraliza a lógica principal da funcionalidade.

### Repository

- Responsável pelo acesso aos dados.
- Não contém regra de negócio.
- Deve ser criado apenas quando houver um caso real de uso.

### Model

- Representa a entidade do domínio.
- Define relacionamentos e propriedades.
- Pode conter scopes simples.
- Não deve concentrar regras de negócio complexas.

### Request

- Responsável apenas pela validação dos dados de entrada.
- Não deve implementar regra de negócio.

## Organização dos Módulos

Cada módulo deve conter apenas os arquivos necessários para a funcionalidade em desenvolvimento.

Exemplo de organização:

```text
Controller
Request
Service
Repository
Model
Policies (quando necessário)
Tests
```

### Diretriz

- Não criar arquivos genéricos sem necessidade.
- Não adicionar estrutura vazia apenas para seguir um padrão.
- Organizar o módulo de forma objetiva e alinhada à funcionalidade atual.

## Banco de Dados

O banco de dados do projeto segue as regras abaixo:

- PostgreSQL/Supabase é o banco oficial de desenvolvimento.
- SQLite é utilizado exclusivamente para testes automatizados.
- Todas as migrations devem ser versionadas.
- Alterações estruturais devem ser discutidas antes da implementação.
- Mudanças no modelo de dados devem ser acompanhadas por migrations apropriadas.

## Testes

Antes de abrir um Pull Request, é obrigatório executar os seguintes comandos:

```bash
php artisan test
php artisan migrate:fresh --seed
npm run build
```

Esses passos devem ser executados com sucesso antes da aprovação do PR.

## Entregas do TCC

Os módulos serão desenvolvidos antecipadamente durante as férias, seguindo o planejamento do TCC.

Quando a faculdade solicitar uma entrega:

- Utilizar a branch do módulo correspondente.
- Garantir que o módulo esteja completo.
- Criar uma tag para registrar a versão entregue, quando isso for relevante.
- Fazer merge para develop apenas quando o módulo estiver validado.

## Versionamento das Entregas

As entregas para a faculdade serão organizadas da seguinte forma:

- Cada módulo será desenvolvido antecipadamente.
- Cada módulo possuirá sua própria branch.
- Quando a faculdade solicitar um módulo, será criada uma Tag representando aquela entrega.
- O histórico permanecerá preservado.
- Não será necessário criar outro repositório apenas para as entregas.
- As Tags servirão como marco oficial de cada versão entregue.

Essa estratégia facilita correções futuras, rastreabilidade e organização do projeto.

## Regras Gerais

- Nunca desenvolver diretamente em main.
- Nunca desenvolver diretamente em develop.
- Nunca desenvolver diretamente em module/*.
- Sempre utilizar feature/* para desenvolvimento individual.
- Manter commits pequenos e objetivos.
- Não misturar funcionalidades diferentes na mesma branch.
- Sempre atualizar a branch antes de abrir um PR.
- Documentar decisões importantes.
- Manter a arquitetura consistente.
- Evitar duplicação de código.
- Priorizar simplicidade.
- Implementar apenas funcionalidades previstas para o módulo atual.

---

Este guia deve ser seguido por toda a equipe como referência oficial para o desenvolvimento do projeto CoSphere.
