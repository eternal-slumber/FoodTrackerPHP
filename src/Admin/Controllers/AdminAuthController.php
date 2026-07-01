<?php

declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Admin\Auth\AdminLoginGuard;
use App\Admin\Auth\AdminSession;
use App\Admin\Config\AdminConfig;
use App\Admin\Repositories\AdminUserRepository;
use App\Core\DatabaseConnection;
use App\Http\ResponseResponder;
use App\View\ViewRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class AdminAuthController
{
    public function __construct(
        private readonly AdminConfig $adminConfig,
        private readonly AdminSession $adminSession,
        private readonly AdminLoginGuard $loginGuard,
        private readonly AdminUserRepository $adminUsers,
        private readonly DatabaseConnection $database,
        private readonly ViewRenderer $viewRenderer
    ) {}

    public function login(Request $request, Response $response): Response
    {
        if ($this->adminSession->currentAdminId() !== null) {
            return $this->redirect($response, $this->adminConfig->path . '/dashboard');
        }

        return ResponseResponder::html($response, $this->renderLoginTemplate(
            $this->database->isAvailable()
        ));
    }

    public function authenticate(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $login = trim((string)($body['login'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $ipAddress = $this->clientIp($request);

        if ($login === '' || $password === '') {
            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => 'Введите логин и пароль',
            ], 400);
        }

        try {
            $limitStatus = $this->loginGuard->status($login, $ipAddress);
            if ($limitStatus['blocked']) {
                $this->writeAuthAuditLog(null, 'login_rate_limited', $request);

                return ResponseResponder::json($response, [
                    'status' => 'error',
                    'message' => 'Слишком много неудачных попыток. Попробуйте позже.',
                ], 429)->withHeader('Retry-After', (string)$limitStatus['retry_after']);
            }

            $admin = $this->adminUsers->findActiveByLogin($login);
        } catch (Throwable) {
            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => 'База данных недоступна. Авторизация временно невозможна.',
            ], 503);
        }

        if ($admin === null || !is_string($admin['password_hash']) || !password_verify($password, $admin['password_hash'])) {
            try {
                $this->loginGuard->registerFailure($login, $ipAddress);
            } catch (Throwable) {
                return ResponseResponder::json($response, [
                    'status' => 'error',
                    'message' => 'База данных недоступна. Авторизация временно невозможна.',
                ], 503);
            }

            $this->writeAuthAuditLog($admin !== null ? (int)$admin['id'] : null, 'login_failed', $request);

            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => 'Неверный логин или пароль',
            ], 403);
        }

        $adminUserId = (int)$admin['id'];
        try {
            $this->adminUsers->markLogin($adminUserId);
        } catch (Throwable $exception) {
            error_log('Admin last login update failed: ' . $exception->getMessage());
        }
        $this->writeAuthAuditLog($adminUserId, 'login_success', $request);
        $this->adminSession->signIn($adminUserId);

        return ResponseResponder::json($response, [
            'status' => 'success',
            'redirect' => $this->adminConfig->path . '/dashboard',
            'admin' => [
                'username' => $admin['username'],
                'role' => $admin['role'],
            ],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $adminId = $this->adminSession->currentAdminId();
        if ($adminId !== null) {
            $this->writeAuthAuditLog($adminId, 'logout', $request);
        }

        $this->adminSession->destroy();

        return ResponseResponder::json($response, [
            'status' => 'success',
            'redirect' => $this->adminConfig->path,
        ]);
    }

    private function renderLoginTemplate(bool $databaseAvailable): string
    {
        return $this->viewRenderer->render('admin/login.html', [
            'ADMIN_PATH' => htmlspecialchars($this->adminConfig->path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'ADMIN_PATH_JSON' => json_encode($this->adminConfig->path, JSON_THROW_ON_ERROR),
            'ADMIN_CSRF_TOKEN_JSON' => json_encode($this->adminSession->csrfToken(), JSON_THROW_ON_ERROR),
            'DATABASE_STATUS_CLASS' => $databaseAvailable ? 'admin-badge-ok' : 'admin-badge-danger',
            'DATABASE_STATUS_TEXT' => $databaseAvailable ? 'База данных доступна' : 'База данных недоступна',
        ]);
    }

    private function clientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        return (string)($serverParams['REMOTE_ADDR'] ?? '');
    }

    private function writeAuthAuditLog(?int $adminUserId, string $action, Request $request): void
    {
        try {
            $this->adminUsers->writeAuthAuditLog(
                $adminUserId,
                $action,
                $this->clientIp($request),
                $request->getHeaderLine('User-Agent')
            );
        } catch (Throwable $exception) {
            error_log('Admin auth audit write failed: ' . $exception->getMessage());
        }
    }

    private function redirect(Response $response, string $location): Response
    {
        return $response
            ->withHeader('Location', $location)
            ->withStatus(302);
    }
}
