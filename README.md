# Mini App для трекинга питания

Telegram Mini App для отслеживания питания и калорий. Анализирует фото еды через AI API и считает ежедневное потребление калорий.

## Возможности

- Регистрация (3-шаговая форма: базовые данные → активность → цель)
- Анализ фото еды через OpenAI-compatible AI provider: OpenRouter или локальный LM Studio
- Подсчет калорий по формуле
- Прогресс-бар потребления калорий за день
- История приемов пищи
- Telegram-бот как панель быстрого доступа: `/start`, `/summary`, `/eat`, `/help`
- Серверная проверка Telegram Mini App auth data
- Приватная выдача загруженных фото через authenticated API

## Технологии

- **Backend:** PHP 8.5, Slim 4, PHP-DI
- **Frontend:** HTML/CSS/JS (Telegram WebApp API)
- **Database:** MySQL
- **AI:** OpenAI-compatible API: OpenRouter или LM Studio
- **Host** Ngrok free

## Архитектура и безопасность

- Slim 4 отвечает за HTTP routing и middleware.
- PHP-DI собирает контроллеры, сервисы и repositories.
- API endpoints под `/api/*` требуют заголовок `X-Telegram-Init-Data`.
- Backend проверяет подпись Telegram `initData` через `TELEGRAM_BOT_TOKEN`.
- Telegram Bot webhook живет отдельно на `/telegram/webhook` и не заменяет Mini App.
- Webhook проверяет `X-Telegram-Bot-Api-Secret-Token`, если задан `TELEGRAM_WEBHOOK_SECRET_TOKEN`.
- `tg_id` из query/body не используется как источник авторизации.
- SQL вынесен в repository layer (`UserRepository`, `MealRepository`).
- Загруженные фото проверяются через `finfo` и разрешены только JPEG/PNG/WebP.
- Прямой доступ к `/storage` закрыт в nginx; фото выдаются через `/api/meals/{id}/image` с проверкой владельца.
- Upload и AI-запросы ограничиваются через MySQL-backed rate limits.

## Установка

1. Клонировать репозиторий
2. `composer install`
3. Создать `.env` файл (см. `.env.example`)
4. Создать базу данных из `schema.sql`
5. Запустить миграции: `composer migrate`
6. Настроить веб-сервер на `public/`

## Docker запуск

Обычный запуск поднимает только Mini App, Nginx и БД. Незавершённая админка в него не входит:

```bash
docker compose --env-file .env.db -f docker/docker-compose.yml up -d
docker compose --env-file .env.db -f docker/docker-compose.yml run --rm app composer install
docker compose --env-file .env.db -f docker/docker-compose.yml run --rm app composer migrate
```

Админка подключается отдельно только через Docker profile после настройки `.env.admin`:

```bash
docker compose --env-file .env.db -f docker/docker-compose.yml --profile admin up -d
```

Для основного приложения оставляй `ADMIN_ENABLED=false`. В admin-контейнере должны быть
`ADMIN_ENABLED=true`, `ADMIN_ONLY=true` и отдельный непредсказуемый `ADMIN_PATH`.

## Web root / DocumentRoot

Production и локальный Apache/MAMP должны обслуживать только директорию `public/`.
Нельзя направлять web root на корень проекта, иначе наружу могут попасть `.env`, `vendor/`, `storage/`, `schema.sql` и другие внутренние файлы.

Правильный DocumentRoot для MAMP:

```text
/Applications/MAMP/htdocs/foodTracker/public
```

Пример Apache VirtualHost лежит в `deploy/apache/foodtracker.local.conf.example`.
В Docker/Nginx это уже настроено через `root /var/www/public`.

После настройки проверь:

```bash
curl -I http://localhost/.env
curl -I http://localhost/schema.sql
curl -I http://localhost/vendor/autoload.php
curl -I http://localhost/storage/uploads/
curl -I http://localhost/
```

Ожидаемо: внутренние пути возвращают `403` или `404`, главная страница возвращает `200`.

## Local UX testing without Telegram

Для быстрой проверки интерфейса можно открыть приложение прямо в браузере без Telegram и ngrok.
Этот режим предназначен только для локальной разработки.

В `.env` включи:

```dotenv
APP_ENV=local
TELEGRAM_DEV_AUTH_ENABLED=true
TELEGRAM_DEV_USER_ID=100001
TELEGRAM_DEV_USERNAME=dev_user
```

После этого открой локальный URL, например `http://localhost:8080`.
Frontend подставит mock Telegram WebApp API, а backend будет считать запросы авторизованными от dev-пользователя.

Никогда не включай `TELEGRAM_DEV_AUTH_ENABLED=true` на публичном сервере.
В production должны быть:

```dotenv
APP_ENV=production
TELEGRAM_DEV_AUTH_ENABLED=false
```

## Local AI через LM Studio

Чтобы не тратить внешние AI-запросы во время разработки, можно переключить приложение на локальный сервер LM Studio.

1. В LM Studio скачай модель.
2. Открой `Developer` / `Local Server`.
3. Запусти OpenAI-compatible server.
4. Укажи в `.env`:

```dotenv
AI_PROVIDER=lmstudio
AI_BASE_URL=http://127.0.0.1:1234/v1
AI_API_KEY=lm-studio
AI_MODEL=название-модели-из-LM-Studio
```

Если PHP работает внутри Docker, `127.0.0.1` будет указывать на контейнер. Тогда используй:

```dotenv
AI_BASE_URL=http://host.docker.internal:1234/v1
```

Для анализа фото нужна vision/multimodal модель. Обычная текстовая модель подойдет только для ручного расчета КБЖУ по названию продукта.

## Telegram Bot

Бот используется как панель быстрого доступа к данным Mini App, а не как отдельный продукт.

Команды:

```text
/start   приветствие и быстрые кнопки
/summary сводка за сегодня
/eat     рекомендация, что съесть сейчас
/help    список команд
```

Под приветствием показываются кнопки:

```text
Сводка за сегодня
Что съесть
Настроить напоминания
Открыть приложение
```

Сводка берется из той же БД и тех же сервисов, что и Mini App. Формат ответа:

```text
Сегодня ты съел 1450 / 2200 ккал.

БЖУ:
Белки: 82 / 130 г
Жиры: 78 / 65 г
Углеводы: 160 / 250 г

Осталось 750 ккал.
```

Кнопка `Что съесть` делает AI-рекомендацию с учетом текущего приема пищи (`завтрак`, `обед`, `ужин`) и дневных остатков/переборов по калориям, белкам, жирам и углеводам. После рекомендации показываются кнопки `Назад` и `Добавить еду`; `Добавить еду` открывает Mini App.

Настройки:

```dotenv
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET_TOKEN=
TELEGRAM_MINI_APP_URL=https://example.com
TELEGRAM_BOT_DEFAULT_TZ_OFFSET_MINUTES=0
```

`TELEGRAM_BOT_DEFAULT_TZ_OFFSET_MINUTES` использует тот же знак, что `Date.getTimezoneOffset()` в браузере: Москва `-180`, UTC `0`. Нужен потому, что обычные Telegram bot updates не передают часовой пояс пользователя.

Webhook регистрируется на публичный URL:

```bash
curl "https://api.telegram.org/bot<token>/setWebhook?url=https://example.com/telegram/webhook&secret_token=<secret>"
```

## Структура БД

Базовая схема лежит в `schema.sql`, точечные изменения — в `database/migrations/`.

## Очистка загруженных фото

Фото из сохраненных приемов удаляются вместе с приемом. При удалении профиля удаляется папка пользователя в `storage/uploads/user_<telegram_id>`.

Если пользователь загрузил фото в черновик, но не сохранил прием, файл остается orphan-файлом. Для их периодической очистки есть команда:

```bash
composer cleanup:uploads
```

По умолчанию удаляются только orphan-файлы старше 24 часов. TTL можно изменить через:

```dotenv
UPLOAD_ORPHAN_TTL_SECONDS=86400
```

Не запускай cleanup без понимания текущего TTL. Значение `0` означало бы удаление всех orphan-файлов сразу, поэтому команда намеренно отказывается работать с `UPLOAD_ORPHAN_TTL_SECONDS=0`.

## Запуск тестов

```bash
docker compose --env-file .env.db -f docker/docker-compose.yml run --rm app ./vendor/bin/phpunit tests/unit/
```

## Структура проекта

```
foodTracker/
├── bootstrap/           # Общая загрузка env/container/app для web и CLI
├── src/
│   ├── Controllers/    # HTTP контроллеры
│   ├── Models/         # Модели данных
│   ├── Repositories/   # SQL доступ к данным
│   ├── Auth/           # Telegram auth
│   ├── Telegram/       # Telegram Bot API client и обработка команд
│   ├── Services/       # Бизнес-логика
│   ├── Enums/          # Перечисления
│   ├── ValueObjects/   # Value Objects
│   └── Validators/     # Валидация
├── config/             # DI контейнер и маршруты Slim
├── database/           # SQL миграции
├── public/             # Публичная директория
├── storage/            # Загруженные файлы
└── tests/              # Unit тесты
```
