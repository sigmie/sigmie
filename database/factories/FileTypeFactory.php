<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\FileType;
use Illuminate\Database\Eloquent\Factories\Factory;

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
