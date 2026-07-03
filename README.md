# FoodTrackerPHP

FoodTrackerPHP is a Telegram Mini App for tracking meals, calories, and macronutrients.

The app allows users to register, add meals, upload food photos, analyze meals through an OpenAI-compatible AI provider, and view daily nutrition progress. The project is built with PHP 8.5, Slim 4, MySQL, Docker, and vanilla HTML/CSS/JavaScript.

## Features

- Telegram Mini App authentication
- 3-step user registration flow
- Meal tracking and daily calorie progress
- Calories and macronutrients calculation
- Food photo upload
- Photo-based meal analysis through an OpenAI-compatible AI API
- Meal history
- Telegram bot commands: `/start`, `/summary`, `/eat`, `/help`
- Private image access through authenticated API
- MySQL-backed rate limiting for uploads and AI requests
- Notification queue for meal reminders

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
- `Validators`, `Enums`, and `ValueObjects` keep the domain logic cleaner

Slim 4 is used for routing and middleware, while PHP-DI is used for dependency injection.

## Security

- Telegram Mini App `initData` is validated on the backend
- API endpoints under `/api/*` require `X-Telegram-Init-Data`
- `tg_id` from query/body is not trusted as an authorization source
- Uploaded images are validated with `finfo`
- Only JPEG, PNG, and WebP uploads are allowed
- Direct access to `/storage` is blocked by Nginx
- Meal images are served through authenticated API endpoints
- Telegram bot webhook requests can be protected with `X-Telegram-Bot-Api-Secret-Token`
- Upload and AI requests are limited through MySQL-backed rate limits

## Installation

Clone the repository:

```bash
git clone <repository-url>
cd FoodTrackerPHP
