<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    protected $model = \App\Models\Article::class;

    public function definition(): array
    {
        return [
            'title'     => 'ARTICLE — ' . $this->faker->words(2, true),
            'body'      => $this->faker->paragraph(),
            'is_active' => true,
            // domiciliataire_id always overridden explicitly in tests
        ];
    }
}
