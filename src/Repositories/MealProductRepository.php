<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MealProduct;
use PDO;

class MealProductRepository
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param MealProduct[] $products
     */
    public function saveMany(int $mealId, array $products): void
    {
        if ($products === []) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO meal_products (meal_id, name, weight, processing, calories, proteins, fats, carbs)
             VALUES (:meal_id, :name, :weight, :processing, :calories, :proteins, :fats, :carbs)'
        );

        foreach ($products as $product) {
            if (!$product instanceof MealProduct) {
                throw new \InvalidArgumentException('Expected MealProduct instance');
            }

            $stmt->execute([
                'meal_id' => $mealId,
                'name' => $product->name,
                'weight' => $product->weight,
                'processing' => $product->processing,
                'calories' => $product->calories,
                'proteins' => $product->proteins,
                'fats' => $product->fats,
                'carbs' => $product->carbs,
            ]);
        }
    }

    /**
     * @return MealProduct[]
     */
    public function findByMealId(int $mealId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM meal_products WHERE meal_id = ? ORDER BY id ASC');
        $stmt->execute([$mealId]);

        return array_map(
            fn(array $data): MealProduct => $this->hydrate($data),
            $stmt->fetchAll()
        );
    }

    private function hydrate(array $data): MealProduct
    {
        return new MealProduct(
            mealId: (int)$data['meal_id'],
            name: (string)$data['name'],
            weight: (int)$data['weight'],
            processing: (string)($data['processing'] ?? ''),
            calories: (int)$data['calories'],
            proteins: (float)$data['proteins'],
            fats: (float)$data['fats'],
            carbs: (float)$data['carbs'],
            id: (int)$data['id'],
            createdAt: isset($data['created_at']) ? (string)$data['created_at'] : null
        );
    }
}
