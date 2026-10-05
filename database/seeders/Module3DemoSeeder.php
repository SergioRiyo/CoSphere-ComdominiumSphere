<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CommonArea;
use App\Models\Order;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class Module3DemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection();
        $isMemoryTest = app()->environment('testing') && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        $isLocalDemo = $connection->getDriverName() === 'pgsql'
            && $connection->getConfig('host') === '127.0.0.1'
            && $connection->getDatabaseName() === 'cosphere_m3_demo';
        if (! app()->environment(['local', 'testing']) || (! $isMemoryTest && ! $isLocalDemo)) {
            throw new LogicException('A demonstração exige o banco local exclusivo cosphere_m3_demo (ou SQLite em memória durante testes).');
        }
        $password = config('module3-demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new LogicException('Defina MODULE3_DEMO_PASSWORD com pelo menos 12 caracteres; use uma senha temporária exclusiva da demonstração.');
        }

        DB::transaction(function () use ($password): void {
            $unit = Unit::firstOrCreate(['block' => 'DEMO', 'number' => '101'], ['type' => 'apartamento']);
            $otherUnit = Unit::firstOrCreate(['block' => 'DEMO', 'number' => '102'], ['type' => 'apartamento']);
            foreach ([
                ['admin', 'Admin Demo', UserRole::Admin, null],
                ['morador', 'Morador Demo', UserRole::Morador, $unit->id],
                ['porteiro', 'Porteiro Demo', UserRole::Porteiro, null],
                ['vizinho', 'Vizinho Demo', UserRole::Morador, $unit->id],
                ['outra-unidade', 'Outra Unidade Demo', UserRole::Morador, $otherUnit->id],
            ] as [$login, $name, $role, $unitId]) {
                User::firstOrCreate(['email' => $login.'@m3-demo.cosphere.test'], [
                    'name' => $name, 'role' => $role, 'unit_id' => $unitId,
                    'is_active' => true, 'email_verified_at' => now(), 'password' => $password,
                ]);
            }
            foreach ([['Quiosque Demo', false], ['Salão Demo', true]] as [$name, $approval]) {
                CommonArea::firstOrCreate(['name' => $name], [
                    'description' => 'Área exclusiva para demonstração do Módulo 3.',
                    'available_from' => '08:00', 'available_until' => '22:00',
                    'max_reservation_minutes' => 240, 'rules' => 'Respeitar o período reservado.',
                    'status' => 'active', 'requires_approval' => $approval,
                ]);
            }
            $resident = User::where('email', 'morador@m3-demo.cosphere.test')->firstOrFail();
            $doorman = User::where('email', 'porteiro@m3-demo.cosphere.test')->firstOrFail();
            $orders = app(OrderService::class);
            if (! Order::where('tracking_code', 'DEMO-PREVISTA')->exists()) {
                $orders->createExpectedByResident(['description' => 'Livros previstos para a demonstração', 'tracking_code' => 'DEMO-PREVISTA'], $resident);
            }
            if (! Order::where('tracking_code', 'DEMO-RECEBIDA')->exists()) {
                $orders->createUnexpectedByDoorman([
                    'description' => 'Caixa recebida para demonstrar retirada', 'tracking_code' => 'DEMO-RECEBIDA',
                    'unit_id' => $resident->unit_id, 'resident_id' => $resident->id,
                ], $doorman);
            }
        });
    }
}
