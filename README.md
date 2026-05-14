# CoSphere-ComdominiumSphere

Aplicacao Laravel + Inertia + React com ambiente via Docker usando Supabase como banco.

## Rodando o projeto

Tenha apenas o Docker Desktop instalado e em execucao.

1. Clone o repositorio:

```bash
git clone <URL_DO_REPOSITORIO>
cd CoSphere-ComdominiumSphere
```

2. Crie o arquivo `.env`:

```bash
cp .env.example .env
```

3. Preencha no `.env` as credenciais reais do Supabase.

4. Suba o projeto pela primeira vez:

```bash
docker compose up --build -d
```

O comando instala as dependencias, gera a `APP_KEY` se necessario, roda as migrations e sobe a aplicacao.

## Enderecos

- Aplicacao: `http://localhost:8000`
- Vite dev server: `http://localhost:5174`

## Comandos basicos

Subir os containers:

```bash
docker compose up -d
```

Parar o projeto:

```bash
docker compose down
```
