# CoSphere — Sistema Integrado de Gestão Condominial

O CoSphere é uma aplicação web desenvolvida por **Sergio Riyó Oshiro e Yasmin Ferreira** como Trabalho de Conclusão de Curso de Engenharia de Software no Centro Universitário da Grande Dourados (UNIGRAN), em 2026. O projeto teve orientação do Prof. M.Sc. Ezefferth Chlysman Araujo Fernandes.

Quando as autorizações de visita ficam em mensagens, as reservas em planilhas e as entregas em um caderno, responder a uma pergunta simples pode exigir várias consultas. O CoSphere reúne esses registros em um só sistema. Moradores, portaria e administração acompanham cada atividade de acordo com seu papel no condomínio.

[Acessar o CoSphere](https://cosphere-comdominiumsphere.onrender.com)

## Como o condomínio usa o CoSphere

| Rotina | Como funciona |
| --- | --- |
| Visitantes | O morador autoriza a visita. Na chegada, o porteiro lê o QR Code, confere os dados e registra a entrada. Na saída, atualiza o histórico de acesso. |
| Encomendas | A portaria registra a chegada, o destinatário recebe um aviso e os moradores da unidade podem consultar a situação da encomenda até a retirada. |
| Áreas comuns | O morador consulta os horários e solicita a reserva. A administração define quais espaços exigem aprovação e pode bloquear horários para manutenção. |
| Ocorrências e manutenção | O morador abre uma ocorrência e acompanha o atendimento. A administração organiza os serviços e os prestadores responsáveis. |
| Comunicação | A administração publica comunicados e enquetes. Os moradores recebem notificações e podem votar. |
| Gestão administrativa | Administradores gerenciam usuários, consultam relatórios e cadastram cobranças. Os moradores podem consultar as cobranças da unidade e baixar boletos simulados para fins acadêmicos. |

## Tecnologias

A interface usa React, TypeScript e Tailwind CSS. O Inertia.js conecta as telas ao Laravel, que implementa em PHP as regras de negócio e as permissões de cada perfil. A aplicação é hospedada no Render, utiliza PostgreSQL no Supabase e envia e-mails pelo Resend.

## Executar localmente com Docker

Você precisa do Docker com Compose e de um banco PostgreSQL de desenvolvimento acessível pelos contêineres. O banco pode estar no Supabase.

1. Na raiz do repositório, crie o arquivo de configuração se ainda não existir.

   ```bash
   cp -n .env.example .env
   ```

2. Em `.env`, mantenha `APP_ENV=local` e `DB_CONNECTION=pgsql`. Preencha `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` com os dados do banco de desenvolvimento. Use um endereço de banco alcançável de dentro do contêiner.

3. Inicie o ambiente. Na primeira execução, o serviço de preparação instala as dependências, cria as tabelas e carrega os dados de exemplo.

   ```bash
   docker compose up --build -d
   ```

4. Abra a [aplicação local](http://localhost:8000) e entre com uma das contas abaixo.

## Contas de desenvolvimento

O `UserSeeder` cria estas contas na preparação do ambiente local.

| Perfil | E-mail | Senha inicial |
| --- | --- | --- |
| Administrador | `admin@cosphere.test` | `password` |
| Porteiro | `porteiro@cosphere.test` | `password` |
| Morador | `morador@cosphere.test` | `password` |

Para testar o controle de visitantes, abra uma sessão como morador para emitir o QR Code e outra como porteiro para fazer a leitura e confirmar a entrada.

## Comandos do dia a dia

Execute os comandos na raiz do repositório.

| Para quê | Comando |
| --- | --- |
| Iniciar o ambiente já configurado | `docker compose up -d` |
| Conferir o estado dos serviços | `docker compose ps -a` |
| Consultar o log de preparação | `docker compose logs --tail=100 setup` |
| Aplicar migrações pendentes | `docker compose exec app php artisan migrate --no-interaction` |
| Criar contas de desenvolvimento que estejam faltando | `docker compose exec app php artisan db:seed --class=UserSeeder --no-interaction` |
| Encerrar o ambiente | `docker compose down` |
