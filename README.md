#Mini App для трекинга питания

Telegram мини апп для отслеживания питания и калорий. Анализирует фото еды используя ИИ api и считает ежедневное потребление калорий
## Возможности

- Регистрация (3-шаговая форма: базовые данные → активность → цель)
- Анализ фото еды через ИИ api (OpenRouter)
- Подсчет калорий по формуле
- Прогресс-бар потребления калорий за день
- История приемов пищи

## Технологии

- **Backend:** PHP 8.5 
- **Frontend:** HTML/CSS/JS (Telegram WebApp API)
- **Database:** MySQL
- **AI:** OpenRouter API (nvidia/nemotron-nano-12b-v2-vl:free)

## Установка

1. Клонировать репозиторий
2. `composer install`
3. Создать базу данных из `schema.sql`
4. Создать `.env` файл (см. `.env.example`)
5. Настроить веб-сервер (public/ директория)

## Структура БД

См. файл `schema.sql`

## Запуск тестов

```bash
./vendor/bin/phpunit tests/unit/
```

## Структура проекта

```
foodTracker/
├── src/
│   ├── Controllers/    # HTTP контроллеры
│   ├── Models/         # Модели данных
│   ├── Services/       # Бизнес-логика
│   ├── Enums/          # Перечисления
│   ├── ValueObjects/   # Value Objects
│   └── Validators/     # Валидация
├── public/             # Публичная директория
├── storage/            # Загруженные файлы
└── tests/              # Unit тесты
```

## Лицензия

MIT