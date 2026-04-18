<?php 

declare(strict_types=1);

namespace App\Interfaces;

interface AIServiceInterFace
{
    //Принимаем путь к файлу, возвращаем массив с калориями и описанием

    public function analyze(string $imagePath): array;

    
}