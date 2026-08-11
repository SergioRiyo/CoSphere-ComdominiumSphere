# CoSphere — CondominiumSphere

Aplicação para gestão condominial desenvolvida como Trabalho de Conclusão de Curso (TCC) de Engenharia de Software.

## Requisitos

- Docker Desktop e Docker Compose (opção recomendada)
- Ou: PHP 8.3+, Composer 2, Node.js 22+ e PostgreSQL/Supabase

O `Dockerfile` disponibiliza PHP 8.3, Node.js 22 e as extensões necessárias para PostgreSQL e para os testes com SQLite.

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
php artisan test
```

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
php artisan test
```
