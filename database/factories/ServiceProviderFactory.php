<?php

namespace Database\Factories;

use App\Models\ServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceProviderFactory extends Factory
{
    protected $model = ServiceProvider::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),

            'cpf_cnpj' => fake()->unique()->numerify('###########'),

            'phone' => fake()->phoneNumber(),

            'email' => fake()->unique()->companyEmail(),

            'specialty' => fake()->randomElement([
                'Eletricista',
                'Encanador',
                'Jardinagem',
                'Limpeza',
                'Segurança',
                'Manutenção geral',
                'Pintura',
            ]),
        ];
    }
}
