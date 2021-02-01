<?php

namespace Database\Factories;

use App\Models\FileType;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FileTypeFactory extends Factory
{
    protected $model = FileType::class;

    public function definition()
    {
        return [
            'index_alias' => $this->faker->name,
            'location' => $this->faker->url
        ];
    }
}
