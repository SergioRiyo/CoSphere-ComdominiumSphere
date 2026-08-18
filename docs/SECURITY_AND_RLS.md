# Segurança, isolamento por unidade e RLS

## Estado e decisão

Laravel é a primeira camada de autorização do CoSphere. A autenticação é feita
por Fortify com sessão, e o navegador não consulta o Supabase diretamente.
Middleware de papel, Form Requests, Services, filtros de consulta e, quando
existirem recursos individuais, Policies Laravel continuam obrigatórios. RLS
não substitui nenhuma dessas proteções.

RLS será adotado como defesa em profundidade. Seu objetivo é limitar o impacto
de uma consulta incompleta, de um filtro de ownership ausente ou de uma
regressão futura quando a aplicação usar uma role PostgreSQL de runtime sujeita
às policies.

Atualmente, Laravel conecta ao PostgreSQL/Supabase como `postgres`, uma role
com `BYPASSRLS`. Portanto, RLS habilitado e policies eventualmente criadas não
protegem as queries atuais do runtime enquanto essa conexão for mantida.

## Roles PostgreSQL futuras

A implantação separará conceitualmente duas responsabilidades:

- `cosphere_migrator`: migrations, DDL e manutenção controlada;
- `cosphere_app`: runtime da aplicação, com `NOSUPERUSER`, `NOBYPASSRLS`, sem
  ownership das tabelas e sem privilégios de DDL.

Essa separação ainda não foi criada no Supabase principal. A role de runtime
receberá apenas os grants necessários para as tabelas e sequências que usar.

## Identidade e contexto transacional

Fortify continua sendo a fonte da identidade. O Laravel deve derivar somente
`app.user_id` do usuário autenticado no backend. O banco derivará papel e
unidade a partir desse identificador; o navegador nunca fornece `unit_id`,
papel ou outro contexto de RLS.

O contexto deverá ser limitado à transação externa de cada operação usando,
conceitualmente, `set_config('app.user_id', ..., true)` ou `SET LOCAL`. Não
deve ser usado `SET` persistente de sessão. Após `COMMIT` ou `ROLLBACK`, uma
consulta sem novo contexto deverá ser negada.

Essa regra é indispensável com pooling: uma conexão reutilizada não pode levar
o contexto do request A para o request B. Requests anônimos, jobs, comandos
Artisan e fluxos de infraestrutura deverão operar sem contexto de usuário, ou
em uma conexão/privilégio explicitamente apropriado ao seu caso.

## Policies pertinentes ao Módulo 1

O escopo inicial de RLS é restrito a:

- `units`: Morador lê somente sua unidade; Admin lê as unidades necessárias à
  gestão administrativa; Porteiro, ausência de contexto e identificador
  inexistente não leem unidades;
- `users`: somente depois de resolver o bootstrap do Fortify, pois o login e
  outros fluxos de autenticação precisam localizar o usuário antes de existir
  `app.user_id`.

Não antecipar policies para reservas, veículos, visitantes, encomendas,
ocorrências, manutenção ou notificações antes que esses módulos tenham
superfícies web correspondentes.

Quando uma policy precisar consultar o papel e a unidade do ator, ela deverá
derivá-los exclusivamente de `app.user_id`. Caso seja necessária uma função
`SECURITY DEFINER`, ela será mínima, com `search_path` explícito, objetos com
schema qualificado, sem SQL dinâmico, sem parâmetros de papel/unidade e com
`EXECUTE` restrito. A necessidade e o formato dessa função serão validados pela
POC PostgreSQL antes de qualquer uso no Supabase principal.

## Testes e implantação futura

SQLite continua cobrindo os testes rápidos da camada Laravel. RLS deve ter uma
suíte PostgreSQL separada, isolada e descartável, usando uma role sem
`BYPASSRLS` e que não seja owner das tabelas. Ela deve provar acesso do Morador
à própria unidade, bloqueio de outra unidade, leitura administrativa legítima,
negação para Porteiro e ausência de contexto, além de não vazamento entre
transações após `COMMIT` e `ROLLBACK`.

A implementação futura seguirá etapas reversíveis: POC isolada, definição de
roles e grants, policies de `units`, integração transacional do contexto no
Laravel, resolução do bootstrap Fortify para `users` e testes PostgreSQL de
integração. Nenhuma dessas etapas está aplicada ao Supabase principal neste
momento.

## Ambiente local para testes RLS

`compose.rls.yml` é uma composição opt-in e independente do compose principal.
Ela executa PostgreSQL 17, a mesma major do ambiente Supabase atual, com dados
em `tmpfs`, banco `cosphere_rls_poc`, rede Docker exclusiva e porta publicada
somente em `127.0.0.1:55432`. Ela não reutiliza `DB_*`, credenciais ou volumes
do Supabase.

Crie a configuração local a partir do exemplo e inicie o banco:

```bash
cp .env.rls-poc.example .env.rls-poc
docker compose --env-file .env.rls-poc -f compose.rls.yml up -d --wait
```

Verifique o estado e a conexão local:

```bash
docker compose --env-file .env.rls-poc -f compose.rls.yml ps
docker compose --env-file .env.rls-poc -f compose.rls.yml exec -T postgres_rls_test \
  sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT current_database(), current_user, version();"'
```

Para a futura suíte RLS, carregue somente as variáveis locais, valide o
guardrail e execute a configuração PHPUnit dedicada:

```bash
set -a; . ./.env.rls-poc; set +a
sh scripts/run-rls-poc-tests.sh
```

`scripts/verify-rls-poc-environment.sh` falha se o host, porta, banco, usuário
ou SSL não forem valores exclusivos da POC. A conexão Laravel `pgsql_rls` usa
somente `RLS_DB_*` e não possui fallback para `DB_*`. A suíte principal
`php artisan test` continua usando SQLite e não depende desse container.

Para parar e apagar todo o estado descartável:

```bash
docker compose --env-file .env.rls-poc -f compose.rls.yml down -v
```

## POC validada: isolamento de `units`

A POC executa exclusivamente no PostgreSQL local descartável e é coberta por
`tests/Integration/Rls/UnitRlsTest.php`. Ela usa a role `cosphere_app_test`,
com `LOGIN`, `NOSUPERUSER`, `NOBYPASSRLS`, sem ownership de `public.units` e
somente `USAGE` no schema `public`, `SELECT` em `units` e `EXECUTE` na função
auxiliar da POC. A role não recebe `SELECT` em `users`.

`public.rls_current_actor()` é uma função `SECURITY DEFINER` pertencente ao
administrador local da POC. Ela tem `search_path` explícito (`pg_catalog,
pg_temp`), não aceita argumentos, não usa SQL dinâmico e retorna somente o
papel e a unidade do usuário identificado por `app.user_id`. `EXECUTE` é
revogado de `PUBLIC` e concedido somente a `cosphere_app_test`.

`public.units` tem RLS habilitado, sem `FORCE ROW LEVEL SECURITY`, e a policy
`units_select_by_actor` permite:

- Morador: somente a unidade cujo ID corresponde à unidade derivada do usuário;
- Admin: todas as unidades da POC;
- Porteiro, ausência de contexto, valor vazio, valor inválido ou identificador
  inexistente: nenhuma unidade.

Cada teste abre uma transação na conexão `pgsql_rls` como
`cosphere_app_test` e usa `set_config('app.user_id', ..., true)`. A POC prova
que um `SELECT` sem filtro de ownership é limitado pela policy, que uma busca
direta pela unidade de outro morador retorna zero linhas e que o contexto não
vaza após `COMMIT`, `ROLLBACK` ou a transição de Morador A para Morador B na
mesma conexão. Após `COMMIT` ou `ROLLBACK`, `current_setting('app.user_id',
true)` pode ser `NULL` ou string vazia; ambos os estados são tratados como
ausência de contexto e retornam zero linhas.

Essa POC não implementa RLS operacional de `users`, não modifica Fortify e não
troca a role do runtime principal. Ela comprova o mecanismo PostgreSQL local;
a integração ao Supabase exige uma role real sem `BYPASSRLS`, bootstrap seguro
para a autenticação e testes PostgreSQL da conexão de runtime.
