# FoodTrackerPHP

FoodTrackerPHP is a Telegram Mini App for tracking meals, calories, and macronutrients.

The app allows users to register, add meals, upload food photos, analyze meals through an OpenAI-compatible AI provider, and view daily nutrition progress. The project is built with PHP 8.5, Slim 4, MySQL, Docker, and vanilla HTML/CSS/JavaScript.

**Live demo:** [demo.mycaloriebot.ru](https://demo.mycaloriebot.ru)

## Features

- Telegram Mini App authentication
- 3-step user registration flow
- Meal tracking and daily calorie progress
- Calories and macronutrients calculation
- Food photo upload and AI analysis
- Meal history and nutrition insights
- Telegram bot commands and meal reminders
- Trainer sharing through read-only links
- Separate administration panel

## Tech Stack

**Backend**

- PHP 8.5
- Slim 4
- PHP-DI
- MySQL
- Composer

**Frontend**

- HTML
- CSS
- JavaScript
- Telegram WebApp API

**Infrastructure**

- Docker / Docker Compose
- Nginx
- VPS deployment
- HTTPS / domain setup

**AI**

- OpenAI-compatible API
- OpenRouter
- Local LM Studio support for development

## Architecture

The project follows a layered structure:

- `Controllers` handle HTTP requests
- `Services` contain business logic
- `Repositories` handle SQL queries
- `Auth` validates Telegram Mini App authentication
- `Telegram` contains bot-related logic
- `Validators`, `Enums`, and `ValueObjects` keep domain logic isolated

Slim 4 is used for routing and middleware, while PHP-DI is used for dependency injection.

## Security

- Telegram Mini App `initData` is validated on the backend
- API endpoints under `/api/*` require `X-Telegram-Init-Data`
- `tg_id` from query or body data is not trusted for authorization
- Uploaded images are validated by content, MIME type, size, and dimensions
- Only JPEG, PNG, and WebP uploads are allowed
- Direct access to internal files and uploaded images is blocked by Nginx
- Meal images are served through authenticated API endpoints
- Upload and AI requests are protected by MySQL-backed rate limits

## Meal tracking

Users can set their body metrics, activity level, and nutrition goal during registration. Meals are organized by day and meal type, with calories, proteins, fats, carbohydrates, weight, and photos stored for each entry.

The daily view shows consumed calories and macronutrients against personal targets. History and progress indicators make it easy to compare recent days without opening every entry.

## AI-assisted nutrition analysis

A meal can be analyzed from a photo or entered by product name. The AI provider returns an estimated dish name, weight, calories, macronutrients, confidence, and product breakdown.

Responses are validated before they reach the meal draft. Invalid JSON, missing fields, unsupported values, rate limits, authentication errors, and temporary provider failures are handled separately instead of silently producing zero values.

## Nutrition insights

The app calculates daily calorie and macronutrient totals from saved meals. It can generate a short AI insight based on the current balance and suggest what to eat next while accounting for the selected meal type and remaining daily targets.

## Telegram bot and reminders

The Telegram bot provides quick access to the same data as the Mini App:

- `/start` displays quick actions
- `/summary` shows today's nutrition summary
- `/eat` generates a meal recommendation
- `/help` lists available commands

Users can configure meal reminders and an evening summary. Notifications are queued in MySQL and dispatched without duplicating overlapping jobs.

## Trainer sharing

Users can create a read-only link for a trainer or nutrition specialist. Shared access can include profile information, nutrition totals, meal history, and recent daily progress without exposing Telegram authentication data.

The guest view displays calorie progress rings for recent days, allowing the trainer to compare consumption against the daily target before opening a specific day.
