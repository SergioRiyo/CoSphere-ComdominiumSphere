# CoSphere — CondominiumSphere

Aplicação para gestão condominial desenvolvida como Trabalho de Conclusão de Curso (TCC) de Engenharia de Software.

## Estado do MVP

Os Módulos 1 a 5 estão implementados no escopo de MVP do TCC:

- **Módulo 1:** autenticação, perfis e gestão administrativa de usuários;
- **Módulo 2:** visitantes, convites e histórico de entrada e saída;
- **Módulo 3:** áreas comuns, reservas, bloqueios, encomendas e notificações;
- **Módulo 4:** ocorrências, prestadores, manutenção de unidades e áreas comuns e relatórios administrativos de reservas, acessos e ocorrências.
- **Módulo 5:** comunicados, central compartilhada de notificações e enquetes com voto único e resultados agregados.

A autorização operacional é aplicada no Laravel por middleware, Form Requests,
Policies, Services e filtros de ownership. A RLS PostgreSQL existente é uma
prova de conceito isolada para `units`; ela não integra a conexão principal da
aplicação e não substitui a autorização Laravel. Consulte
[`docs/SECURITY_AND_RLS.md`](docs/SECURITY_AND_RLS.md).

As regras, permissões e limitações de comunicados, central de notificações e
enquetes estão registradas em
[`docs/MODULE_05_BUSINESS_RULES.md`](docs/MODULE_05_BUSINESS_RULES.md).

## Requisitos

- Docker Desktop e Docker Compose (opção recomendada)
- Ou: PHP 8.3+, Composer 2, Node.js 22+ e PostgreSQL/Supabase

O `Dockerfile` disponibiliza PHP 8.3, Node.js 22 e a extensão PostgreSQL. A
imagem ainda precisa incorporar GD e `pdo_sqlite` para executar integralmente a
suíte principal dentro do container.

## Setup rápido com Docker

1. Clone o repositório e entre na pasta:

```bash
git clone <URL_DO_REPOSITORIO>
cd CoSphere-ComdominiumSphere
```

2. Copie o arquivo de ambiente e configure a conexão PostgreSQL/Supabase em `.env`:

```bash
cp .env.example .env
```

O `docker-setup.sh` cria `.env` somente se ele não existir. Ele nunca substitui uma configuração existente, incluindo as credenciais do Supabase.

3. Suba o ambiente:

```bash
docker compose up --build -d
```

O primeiro boot instala as dependências, gera a chave da aplicação quando necessário e executa as migrations e seeders no banco configurado para desenvolvimento (PostgreSQL/Supabase). O SQLite é reservado para testes automatizados.

## Endereços

- Aplicação: http://localhost:8000
- Vite dev server: http://localhost:5174

## Credenciais padrão

Após o seed inicial, você pode entrar com:

- E-mail: admin@cosphere.test
- Senha: password

## Comandos úteis

Subir os containers:

```bash
docker compose up -d
```

Parar os containers:

```bash
docker compose down
```

Executar testes:

```bash
php artisan test --compact
```

As suítes de integração PostgreSQL exigem bancos locais, exclusivos e
descartáveis nas portas indicadas pelos respectivos arquivos de configuração:

```bash
php artisan test -c phpunit.module3-pgsql.xml
php artisan test -c phpunit.m4-pgsql.xml
php artisan test -c phpunit.m5-pgsql.xml
```

Essas suítes executam `migrate:fresh` e não devem apontar para bancos de
desenvolvimento, compartilhados ou de produção.

Validações de frontend:

```bash
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

Para recriar o banco de desenvolvimento configurado em `.env`:

```bash
php artisan migrate:fresh --seed
```

> Esse último comando remove os dados do banco de desenvolvimento selecionado. Não o execute apontando para um banco compartilhado ou de produção.

## Dados para demonstração da M5

`Module5DemoSeeder` usa `Module3DemoSeeder` e só executa em `APP_ENV=local`
com PostgreSQL em `127.0.0.1` e banco exclusivo `cosphere_m3_demo`, ou em
SQLite em memória durante testes. Crie esse banco descartável previamente,
configure sua conexão no ambiente local e uma senha temporária exclusiva com
ao menos 12 caracteres em `MODULE3_DEMO_PASSWORD`. Depois execute:

```bash
php artisan migrate --no-interaction
php artisan db:seed --class=Module5DemoSeeder --no-interaction
```

O seeder cria os usuários `admin@m3-demo.cosphere.test`,
`morador@m3-demo.cosphere.test`, `porteiro@m3-demo.cosphere.test`,
`vizinho@m3-demo.cosphere.test` e `outra-unidade@m3-demo.cosphere.test`,
todos com a senha temporária informada; áreas, encomendas, um comunicado publicado
e um rascunho, uma enquete aberta e outra em rascunho. A publicação é feita pelo
serviço real e notifica os três moradores elegíveis. Reexecutar o seeder preserva
votos e não duplica os dados originais enquanto seus títulos não forem alterados.
Para reiniciar uma demonstração, recrie **somente esse banco descartável**.

## Execução local sem Docker

```bash
composer install
npm ci
cp .env.example .env
# Configure DB_CONNECTION=pgsql e as credenciais PostgreSQL/Supabase em .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
npm run dev
```

## Ambiente de testes

`phpunit.xml` e `.env.testing` configuram SQLite em memória, com chaves estrangeiras ativas. A execução dos testes não utiliza a conexão PostgreSQL/Supabase de desenvolvimento.

```bash
php artisan test --compact
```

## Limitações intencionais

Para manter a entrega proporcional ao TCC, o MVP não inclui RLS operacional,
multitenancy, exportação PDF/Excel, gráficos avançados, validação matemática de
CPF, saída do visitante pelo mesmo QR, rastreamento de transportadora, testes
end-to-end de navegador, chat em ocorrências, exclusão completa de usuários ou
mudanças estruturais amplas na central de notificações.
Comunicados não têm anexos, segmentação, reações ou entrega externa; enquetes não
oferecem anonimato criptográfico, alteração de voto, quórum jurídico, votação
ponderada, agendamento automático ou resultados para moradores. A identificação
do voto é armazenada para garantir unicidade, embora não apareça nos resultados.

## Roteiro de validação manual

Antes da apresentação, validar em navegador os fluxos demonstráveis:

- [ ] Administrador gerencia usuários sem conseguir remover o último administrador ativo.
- [ ] Morador visualiza a entrada e a saída de seu visitante.
- [ ] Administrador responde e finaliza uma ocorrência.
- [ ] Administrador cadastra uma manutenção de área comum.
- [ ] A área em manutenção fica indisponível para reserva.
- [ ] Administrador acessa os relatórios de reservas, acessos e ocorrências.
- [ ] Reservas, visitantes, encomendas e ocorrências continuam funcionando em suas jornadas principais.
- [ ] Administrador cria e publica um comunicado; morador encontra o comunicado e sua notificação.
- [ ] Morador consulta a central e marca uma notificação própria como lida; porteiro não acessa a central.
- [ ] Administrador publica uma enquete; morador vota uma vez e não consegue alterar ou repetir o voto.
- [ ] Administrador consulta totais e percentuais agregados e encerra a enquete.

Esse roteiro é uma verificação humana complementar e não substitui as suítes
automatizadas. Testes end-to-end de navegador permanecem fora do escopo do MVP.
