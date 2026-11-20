<?php

declare(strict_types=1);

final class AdminAuth
{
    public const ROLE_PEAKASUTAJA = 'Peakasutaja';
    public const ROLE_KASUTAJA = 'kasutaja';
    public const ROLE_KODANIK = 'kodanik_er';

    /**
     * Получить активную роль из текущей сессии.
     * Проверяет ключи, используемые в LIMIS:
     * - $_SESSION['active_role'] (имя поля из формы выбора аккаунта LIMIS)
     * - $_SESSION['role']
     * - $_SESSION['user']['role']
     * - $_SESSION['user_role']
     */
    public static function getActiveRole(): ?string
    {
        $role = $_SESSION['active_role']
            ?? $_SESSION['role']
            ?? ($_SESSION['user']['role'] ?? null)
            ?? ($_SESSION['user_role'] ?? null);

        if (is_string($role) && trim($role) !== '') {
            return trim($role);
        }

        return null;
    }

    /**
     * Проверка, обладает ли текущая сессия правами Peakasutaja.
     * Проверка выполняется регистронезависимо ('peakasutaja', 'Peakasutaja', 'PEAKASUTAJA'),
     * а также поддерживает составные идентификаторы формата 'peakasutaja:...'.
     */
    public static function isPeakasutaja(): bool
    {
        $role = self::getActiveRole();
        if ($role === null) {
            return false;
        }

        return strcasecmp($role, self::ROLE_PEAKASUTAJA) === 0
            || stripos($role, 'peakasutaja:') === 0;
    }

    /**
     * Проверка, авторизован ли пользователь (присутствует ли любая роль в сессии).
     */
    public static function isAuthenticated(): bool
    {
        return self::getActiveRole() !== null;
    }

    /**
     * Режим аутентификации:
     * - 'standalone' (по умолчанию): демонстрационный режим с отдельным паролем (admin_hash)
     * - 'limis': штатный режим LIMIS (проверка роли в существующей сессии LIMIS)
     */
    public static function getAuthMode(): string
    {
        $mode = cfg()['auth_mode'] ?? 'standalone';
        return in_array($mode, ['limis', 'standalone'], true) ? $mode : 'standalone';
    }

    /**
     * Установить роль в сессию (для standalone/demo входа и тестирования).
     */
    public static function setSessionRole(string $role): void
    {
        $_SESSION['role'] = $role;
        $_SESSION['active_role'] = $role;
    }

    /**
     * Сбросить роль и завершить сессию.
     */
    public static function logout(): void
    {
        unset($_SESSION['role'], $_SESSION['active_role'], $_SESSION['user']);
    }
}
