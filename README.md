# CoSphere-ComdominiumSphere

Aplicação Laravel + Inertia + React para gestão de condomínio, com bootstrap rápido para desenvolvimento local.

## Requisitos

- Docker Desktop (opção recomendada)
- Ou: PHP 8.5+, Composer, Node.js 20+ e PostgreSQL/Supabase

## Setup rápido com Docker

1. Clone o repositório e entre na pasta:

```bash
git clone <URL_DO_REPOSITORIO>
cd CoSphere-ComdominiumSphere
```

2. Copie o arquivo de ambiente:

```bash
cp .env.example .env
```

3. Suba o ambiente:

```bash
docker compose up --build -d
```

O primeiro boot instala dependências, gera a chave da aplicação e executa as migrações e seeders no banco configurado para desenvolvimento (PostgreSQL/Supabase). O SQLite é reservado para testes automatizados.

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

## Execução local sem Docker

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
npm run dev
```
