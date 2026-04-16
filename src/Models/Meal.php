<?php 

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Meal
{
    public function __construct(
        public int $userId,
        public string $description,
        public int $calories,
        public float $proteins = 0,
        public float $fats = 0,
        public float $carbs = 0,
        public ?string $imagePath = null,
        public ?int $id = null,
        public ?string $createdAt = null
    ) {}

    //Сохранение записи о еде в БД

    public function save(): bool
    {
        $db = Database::getConnection();

        $sql = "INSERT INTO meals (user_id, food_description, calories, proteins, fats, carbs, image_path) 
                VALUES (:user_id, :description, :calories, :proteins, :fats, :carbs, :image_path)";

        $stmt = $db->prepare($sql);
        
        return $stmt->execute([
            'user_id'     => $this->userId,
            'description' => $this->description,
            'calories'    => $this->calories,
            'proteins'    => $this->proteins,
            'fats'        => $this->fats,
            'carbs'       => $this->carbs,
            'image_path'  => $this->imagePath
        ]);
    }

    //Поиск всех приемов пищи пользователя

    public static function findByUserId(int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM meals WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);

        $meals = [];
        while ($data = $stmt->fetch()) {
            $meals[] = new self(
                userId: (int)$data['user_id'],
                description: $data['food_description'],
                calories: (int)$data['calories'],
                proteins: (float)$data['proteins'],
                fats: (float)$data['fats'],
                carbs: (float)$data['carbs'],
                imagePath: $data['image_path'],
                id: (int)$data['id'],
                createdAt: $data['created_at']
            );
        }

        return $meals;
    }

    //Поиск приема пищи по ID

    public static function findById(int $id): ?self
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM meals WHERE id = ?");
        $stmt->execute([$id]);

        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }

        return new self(
            userId: (int)$data['user_id'],
            description: $data['food_description'],
            calories: (int)$data['calories'],
            proteins: (float)$data['proteins'],
            fats: (float)$data['fats'],
            carbs: (float)$data['carbs'],
            imagePath: $data['image_path'],
            id: (int)$data['id'],
            createdAt: $data['created_at']
        );
    }

    //Удаление записи о приеме пищи

    public function delete(): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM meals WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
}
