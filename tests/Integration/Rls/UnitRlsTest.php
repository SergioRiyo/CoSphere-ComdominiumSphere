<?php

namespace Tests\Integration\Rls;

use Illuminate\Support\Facades\DB;
use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\BeforeClass;
use Tests\TestCase;

class UnitRlsTest extends TestCase
{
    private const UnitAId = 101;

    private const UnitBId = 102;

    private const ResidentAId = 1001;

    private const ResidentBId = 1002;

    private const AdminId = 1003;

    private const DoormanId = 1004;

    private static bool $pocPrepared = false;

    #[BeforeClass]
    public static function verifyRlsPocEnvironment(): void
    {
        self::assertSame('127.0.0.1', getenv('RLS_DB_HOST'));
        self::assertSame('55432', getenv('RLS_DB_PORT'));
        self::assertSame('cosphere_rls_poc', getenv('RLS_DB_DATABASE'));
        self::assertSame('cosphere_app_test', getenv('RLS_DB_USERNAME'));
        self::assertSame('disable', getenv('RLS_DB_SSLMODE'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$pocPrepared) {
            $this->preparePoc();
            self::$pocPrepared = true;
        }

        DB::purge('pgsql_rls');
    }

    protected function tearDown(): void
    {
        DB::purge('pgsql_rls');

        parent::tearDown();
    }

    public function test_runtime_role_and_policy_have_the_required_structure(): void
    {
        $runtime = $this->runtimePdo();

        $this->assertSame('cosphere_app_test', $this->scalar($runtime, 'SELECT current_user'));

        $role = $this->adminPdo()->query(<<<'SQL'
            SELECT rolcanlogin, rolsuper, rolbypassrls
            FROM pg_roles
            WHERE rolname = 'cosphere_app_test'
        SQL)->fetch();

        $this->assertNotFalse($role);
        $this->assertTrue((bool) $role['rolcanlogin']);
        $this->assertFalse((bool) $role['rolsuper']);
        $this->assertFalse((bool) $role['rolbypassrls']);
        $this->assertSame('cosphere_rls_admin', $this->scalar($this->adminPdo(), <<<'SQL'
            SELECT pg_get_userbyid(relowner)
            FROM pg_class
            WHERE oid = 'public.units'::regclass
        SQL));
        $this->assertFalse((bool) $this->scalar($this->adminPdo(), <<<'SQL'
            SELECT has_table_privilege('cosphere_app_test', 'public.users', 'SELECT')
        SQL));

        $table = $this->adminPdo()->query(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.units'::regclass
        SQL)->fetch();

        $this->assertNotFalse($table);
        $this->assertTrue((bool) $table['relrowsecurity']);
        $this->assertFalse((bool) $table['relforcerowsecurity']);
        $this->assertSame('units_select_by_actor', $this->scalar($this->adminPdo(), <<<'SQL'
            SELECT policyname
            FROM pg_policies
            WHERE schemaname = 'public'
              AND tablename = 'units'
        SQL));
    }

    public function test_resident_a_sees_only_unit_a_even_without_an_application_filter(): void
    {
        $runtime = $this->runtimePdo();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::ResidentAId);

        $this->assertSame((string) self::ResidentAId, $this->currentActor($runtime));
        $this->assertSame([self::UnitAId], $this->visibleUnitIds($runtime));
        $this->assertSame([], $this->visibleUnitIds($runtime, self::UnitBId));

        $runtime->rollBack();
    }

    public function test_resident_b_sees_only_unit_b(): void
    {
        $runtime = $this->runtimePdo();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::ResidentBId);

        $this->assertSame([self::UnitBId], $this->visibleUnitIds($runtime));

        $runtime->rollBack();
    }

    public function test_admin_sees_all_units_and_doorman_sees_none(): void
    {
        $runtime = $this->runtimePdo();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::AdminId);
        $this->assertSame([self::UnitAId, self::UnitBId], $this->visibleUnitIds($runtime));
        $runtime->commit();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::DoormanId);
        $this->assertSame([], $this->visibleUnitIds($runtime));
        $runtime->rollBack();
    }

    public function test_absent_unknown_empty_and_invalid_context_fail_closed(): void
    {
        $runtime = $this->runtimePdo();

        $runtime->beginTransaction();
        $this->assertContains($this->currentActor($runtime), [null, '']);
        $this->assertSame([], $this->visibleUnitIds($runtime));
        $runtime->commit();

        foreach (['999999', '', 'not-a-user-id'] as $actorId) {
            $runtime->beginTransaction();
            $this->setActor($runtime, $actorId);
            $this->assertSame($actorId, $this->currentActor($runtime));
            $this->assertSame([], $this->visibleUnitIds($runtime));
            $runtime->rollBack();
        }
    }

    public function test_commit_resets_context_on_the_same_connection(): void
    {
        $runtime = $this->runtimePdo();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::ResidentAId);
        $this->assertSame([self::UnitAId], $this->visibleUnitIds($runtime));
        $runtime->commit();

        $runtime->beginTransaction();
        $this->assertContains($this->currentActor($runtime), [null, '']);
        $this->assertSame([], $this->visibleUnitIds($runtime));
        $runtime->rollBack();
    }

    public function test_rollback_resets_context_on_the_same_connection(): void
    {
        $runtime = $this->runtimePdo();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::ResidentAId);
        $this->assertSame([self::UnitAId], $this->visibleUnitIds($runtime));
        $runtime->rollBack();

        $runtime->beginTransaction();
        $this->assertContains($this->currentActor($runtime), [null, '']);
        $this->assertSame([], $this->visibleUnitIds($runtime));
        $runtime->rollBack();
    }

    public function test_a_to_b_context_transition_does_not_leak(): void
    {
        $runtime = $this->runtimePdo();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::ResidentAId);
        $this->assertSame([self::UnitAId], $this->visibleUnitIds($runtime));
        $runtime->commit();

        $runtime->beginTransaction();
        $this->setActor($runtime, (string) self::ResidentBId);
        $this->assertSame([self::UnitBId], $this->visibleUnitIds($runtime));
        $runtime->rollBack();
    }

    private function preparePoc(): void
    {
        $admin = $this->adminPdo();

        $this->assertSame('cosphere_rls_poc', $this->scalar($admin, 'SELECT current_database()'));
        $this->assertSame('cosphere_rls_admin', $this->scalar($admin, 'SELECT current_user'));
        $this->assertSame('units', $this->scalar($admin, "SELECT to_regclass('public.units')::text"));
        $this->assertSame('users', $this->scalar($admin, "SELECT to_regclass('public.users')::text"));

        $admin->exec(<<<'SQL'
            DROP POLICY IF EXISTS units_select_by_actor ON public.units;
            ALTER TABLE public.units DISABLE ROW LEVEL SECURITY;
            DROP FUNCTION IF EXISTS public.rls_current_actor();
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'cosphere_app_test') THEN
                    REVOKE ALL PRIVILEGES ON TABLE public.units FROM cosphere_app_test;
                    REVOKE USAGE ON SCHEMA public FROM cosphere_app_test;
                    DROP ROLE cosphere_app_test;
                END IF;
            END
            $$;
            TRUNCATE TABLE public.users, public.units RESTART IDENTITY CASCADE;
        SQL);

        $runtimePassword = $admin->quote((string) getenv('RLS_DB_PASSWORD'));

        $admin->exec(<<<SQL
            BEGIN;
            CREATE ROLE cosphere_app_test
                LOGIN
                NOSUPERUSER
                NOBYPASSRLS
                NOCREATEDB
                NOCREATEROLE
                NOREPLICATION
                PASSWORD {$runtimePassword};
            REVOKE ALL ON SCHEMA public FROM PUBLIC;
            GRANT USAGE ON SCHEMA public TO cosphere_app_test;
            REVOKE ALL ON TABLE public.units FROM PUBLIC;
            REVOKE ALL ON TABLE public.users FROM PUBLIC;
            GRANT SELECT ON TABLE public.units TO cosphere_app_test;
            CREATE FUNCTION public.rls_current_actor()
            RETURNS TABLE(actor_role text, actor_unit_id bigint)
            LANGUAGE sql
            STABLE
            SECURITY DEFINER
            SET search_path = pg_catalog, pg_temp
            AS $$
                WITH request_context AS (
                    SELECT NULLIF(pg_catalog.current_setting('app.user_id', true), '') AS actor_id
                )
                SELECT u.role::text, u.unit_id
                FROM public.users AS u
                CROSS JOIN request_context AS context
                WHERE u.id = CASE
                    WHEN context.actor_id ~ '^[0-9]+$' THEN context.actor_id::bigint
                    ELSE NULL
                END
            $$;
            REVOKE ALL ON FUNCTION public.rls_current_actor() FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION public.rls_current_actor() TO cosphere_app_test;
            ALTER TABLE public.units ENABLE ROW LEVEL SECURITY;
            CREATE POLICY units_select_by_actor
                ON public.units
                FOR SELECT
                TO cosphere_app_test
                USING (
                    EXISTS (
                        SELECT 1
                        FROM public.rls_current_actor() AS actor
                        WHERE actor.actor_role = 'admin'
                           OR (actor.actor_role = 'morador' AND actor.actor_unit_id = units.id)
                    )
                );
            INSERT INTO public.units (id, block, number, type, status, created_at, updated_at)
            VALUES
                (101, 'Bloco A', 'A-101', 'Apartamento', 'active', NOW(), NOW()),
                (102, 'Bloco B', 'B-202', 'Apartamento', 'active', NOW(), NOW());
            INSERT INTO public.users (id, unit_id, name, email, role, password, is_active, created_at, updated_at)
            VALUES
                (1001, 101, 'Morador A', 'morador.a@rls-poc.test', 'morador', 'not-used', true, NOW(), NOW()),
                (1002, 102, 'Morador B', 'morador.b@rls-poc.test', 'morador', 'not-used', true, NOW(), NOW()),
                (1003, NULL, 'Admin POC', 'admin@rls-poc.test', 'admin', 'not-used', true, NOW(), NOW()),
                (1004, NULL, 'Porteiro POC', 'porteiro@rls-poc.test', 'porteiro', 'not-used', true, NOW(), NOW());
            COMMIT;
        SQL);
    }

    private function adminPdo(): PDO
    {
        $host = (string) getenv('RLS_DB_HOST');
        $port = (string) getenv('RLS_DB_PORT');
        $database = (string) getenv('RLS_POSTGRES_DATABASE');
        $username = (string) getenv('RLS_POSTGRES_ADMIN_USERNAME');
        $password = (string) getenv('RLS_POSTGRES_ADMIN_PASSWORD');

        if ($host !== '127.0.0.1' || $port !== '55432' || $database !== 'cosphere_rls_poc' || $username !== 'cosphere_rls_admin') {
            throw new LogicException('RLS POC administration is restricted to the local disposable PostgreSQL database.');
        }

        return new PDO(
            "pgsql:host={$host};port={$port};dbname={$database};sslmode=disable",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function runtimePdo(): PDO
    {
        return DB::connection('pgsql_rls')->getPdo();
    }

    private function setActor(PDO $connection, string $actorId): void
    {
        $statement = $connection->prepare("SELECT pg_catalog.set_config('app.user_id', ?, true)");
        $statement->execute([$actorId]);
    }

    private function currentActor(PDO $connection): ?string
    {
        return $this->scalar($connection, "SELECT pg_catalog.current_setting('app.user_id', true)");
    }

    /**
     * @return list<int>
     */
    private function visibleUnitIds(PDO $connection, ?int $id = null): array
    {
        $statement = $connection->prepare(
            $id === null
                ? 'SELECT id FROM public.units ORDER BY id'
                : 'SELECT id FROM public.units WHERE id = ? ORDER BY id',
        );
        $statement->execute($id === null ? [] : [$id]);

        return array_map(
            static fn (mixed $unitId): int => (int) $unitId,
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    private function scalar(PDO $connection, string $query): ?string
    {
        $value = $connection->query($query)->fetchColumn();

        return $value === false ? null : (string) $value;
    }
}
