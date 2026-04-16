<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AIServiceInterFace;

class OpenRouterService implements AIServiceInterFace // OpenRouter API Service
{
    public function __construct(private readonly string $apiKey)
    { }

    public function analyze(string $imagePath): array
    {
        $imageData = base64_encode(file_get_contents($imagePath));
        $url = "https://openrouter.ai/api/v1/chat/completions";

        // Промпт: профессиональный диетолог (используется OpenRouter API с моделью nvidia/nemotron-nano-12b-v2-vl:free)
        $prompt = "Ты — профессиональный диетолог. Проанализируй фото еды. 
        Если на фото еда постарайся максимально точно подсчитать калории: верни JSON: {\"food\": \"название\", \"kcal\": 123}. 
        Если на фото НЕ еда: верни JSON: {\"food\": \"не определено\", \"kcal\": 0}. 
        Пиши ТОЛЬКО JSON, без лишнего текста.";
        
        $payload = [
            "model" => "nvidia/nemotron-nano-12b-v2-vl:free",
            "messages" => [[
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $prompt],
                    ["type" => "image_url", "image_url" => ["url" => "data:image/jpeg;base64," . $imageData]]
                ]
            ]]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            error_log("OpenRouter API Error: HTTP $httpCode");
            error_log("OpenRouter API Response: $response");
            error_log("OpenRouter API URL: $url");
            return ['food' => 'Ошибка API', 'kcal' => 0];
        }

        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("OpenRouter JSON Error: " . json_last_error_msg());
            return ['food' => 'Ошибка обработки', 'kcal' => 0];
        }
        
        // Достаем текст из ответа OpenRouter и парсим его как JSON
        $textResponse = $result['choices'][0]['message']['content'] ?? '{}';
        $parsed = json_decode(trim($textResponse, " `\n\r\t"), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("OpenRouter Response Parse Error: " . json_last_error_msg() . " - Text: $textResponse");
            return ['food' => 'Не определено', 'kcal' => 0];
        }
        
        return $parsed;
    }
}
