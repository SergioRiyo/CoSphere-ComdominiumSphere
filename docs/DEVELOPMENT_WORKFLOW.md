# Desenvolvimento do Projeto

Este é o guia oficial de desenvolvimento do CoSphere, TCC de Engenharia de Software. Ele descreve a base técnica entregue no Módulo 0 e o fluxo para os módulos do TCC II.

## Estado e stack verificados

O código e os arquivos de dependência são a fonte de verdade da stack:

- PHP 8.3+ (`composer.json` e imagem Docker PHP 8.3);
- Laravel 13.9;
- Inertia Laravel 3.1 e React 19.2;
- TypeScript 5.9, Vite 8.0 e Tailwind CSS 4.3;
- PostgreSQL/Supabase como banco de desenvolvimento;
- SQLite em memória exclusivamente para testes;
- PHPUnit 12.5;
- Docker Compose, Node.js 22 e NPM.

## Módulo 0 — preparação técnica

O Módulo 0 contém a configuração Laravel + Inertia + React, Docker, banco, migrations, models, enums, services, factories, seeders e testes da base. Ele não entrega fluxos funcionais dos módulos seguintes.

### Ambiente local

```bash
composer install
npm ci
cp .env.example .env
# Configure PostgreSQL/Supabase no arquivo .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
npm run dev
```

O banco de desenvolvimento usa PostgreSQL/Supabase. A configuração de exemplo começa com `DB_CONNECTION=pgsql`; substitua host, banco, usuário e senha pelos valores do ambiente escolhido.

### Ambiente Docker

```bash
cp .env.example .env
# Configure PostgreSQL/Supabase no arquivo .env
docker compose up --build -d
```

O serviço `setup` instala dependências, cria a chave da aplicação quando ausente, cria o link de storage e executa migrations com seed. O script de setup somente cria `.env` se ele ainda não existir; uma configuração válida nunca é sobrescrita.

Endereços padrão:

- Aplicação: `http://localhost:8000`
- Vite: `http://localhost:5174`

### Testes e validações

O ambiente de testes é definido em `phpunit.xml` e `.env.testing`, com SQLite em memória e chaves estrangeiras ativas. Ele não acessa o banco PostgreSQL/Supabase de desenvolvimento.

```bash
php artisan test
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

Para validar migrations e seeders de forma isolada, execute:

```bash
php artisan migrate:fresh --seed --env=testing
```

Para recriar o banco configurado no `.env` de desenvolvimento:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` apaga todas as tabelas da conexão selecionada. Não o execute em um banco compartilhado ou de produção.

## Arquitetura adotada

O projeto preserva o fluxo abaixo quando a funcionalidade exigir essas camadas:

```text
Controller → Service → Repository → Model
```

- Controllers recebem a requisição e retornam a resposta.
- Services concentram regras de negócio.
- Repositories devem existir somente quando houver uma necessidade concreta de acesso a dados; o Módulo 0 não possui repositories ou contracts a serem vinculados.
- Models representam entidades, casts e relacionamentos.
- Form Requests validam dados de entrada; autorização específica será adicionada somente no módulo responsável.

Não introduzir abstrações vazias, microservices, CQRS ou event sourcing. Antes de criar código, verificar se já existe uma convenção ou componente equivalente no projeto.

## TCC II — divisão oficial

| Módulo | Escopo | Status |
| --- | --- | --- |
| Módulo 1 | Base, autenticação e usuários | Concluído (MVP) |
| Módulo 2 | Visitantes e controle de acesso | Pendente |
| Módulo 3 | Reservas e encomendas | Pendente |
| Módulo 4 | Ocorrências, manutenção e relatórios | Pendente |
| Módulo 5 | Comunicação, comunidade e integração | Pendente |

No Módulo 1, a autorização principal é aplicada pelo Laravel com autenticação,
status e papéis. A RLS foi validada como prova de conceito PostgreSQL para o
isolamento de unidades e não representa uma integração completa ao runtime.

O Módulo 0 é somente a fundação técnica. Não antecipar telas, CRUDs, controle de acesso por perfil, RLS, policies ou regras de negócio desses módulos em correções da base.

## Fluxo Git

```text
main
└── develop
    ├── fix/module-0-hardening
    ├── module/01-base-autenticacao-usuarios
    ├── module/02-visitantes-acesso
    ├── module/03-reservas-encomendas
    ├── module/04-ocorrencias-manutencao-relatorios
    └── module/05-comunicacao-comunidade-integracao
```

- `main` contém versões estáveis e entregas relevantes.
- `develop` é a linha de integração.
- Cada módulo é criado a partir de `develop`.
- Cada pessoa trabalha em uma `feature/*` derivada da branch do módulo e abre Pull Request para ela.
- Correções técnicas isoladas podem usar `fix/*`, como `fix/module-0-hardening`.
- Não fazer merge direto em `main` ou `develop`; não reescrever histórico e não fazer force push.

## Convenções de commits e Pull Requests

Usar Conventional Commits, com mensagens pequenas e semânticas:

```text
fix(setup): preserve existing environment configuration
fix(database): complete vehicle base model
test: stabilize database seeder expectations
docs: align module workflow and environment setup
```

Antes de abrir um Pull Request, executar as validações aplicáveis e registrar o resultado. O PR deve incluir descrição objetiva, testes executados, impacto em migrations e atualização de documentação quando houver alteração de setup ou fluxo.

## Pendências que não pertencem ao Módulo 0

- Módulo 1 (MVP concluído): uma eventual evolução para produção pode integrar a
  RLS ao runtime e ampliar o hardening, sem fazer parte da entrega atual.
- Módulo 2: implementar a validação de vínculo e papéis no fluxo de visitantes e controle de acesso.
- Módulo 3: implementar autorização e fluxos de reservas e encomendas.
- Módulo 4: implementar regras de ocorrências, manutenção e relatórios.
- Módulo 5: implementar canais e entregas de comunicação comunitária.
