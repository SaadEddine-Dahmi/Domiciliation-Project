<?php
// database/factories/ArticleFactory.php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'domiciliataire_id' => User::factory()->domiciliataire(),
            'title'             => fake()->sentence(4),
            'body'              => fake()->paragraph(),
            'is_active'         => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
