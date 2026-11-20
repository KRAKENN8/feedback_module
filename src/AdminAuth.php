<?php

declare(strict_types=1);

/**
 * Аутентификация и проверка ролей для административного интерфейса tagasiside.
 *
 * Поддерживает два режима работы:
 * - 'limis': штатный режим интеграции, где роль считывается из сессии пользователя LIMIS.
 * - 'standalone': автономный режим с аутентификацией по паролю из конфигурации.
 */
final class AdminAuth
{
    /** @var string Основная привилегированная роль LIMIS с доступом к статистике */
    public const ROLE_PEAKASUTAJA = 'Peakasutaja';

    /** @var string Стандартная роль сотрудника без прав просмотра статистики */
    public const ROLE_KASUTAJA = 'kasutaja';

    /** @var string Роль физического лица (жителя) */
    public const ROLE_KODANIK = 'kodanik_er';

    /**
     * Получить активную роль из текущей сессии.
     * Проверяет ключи, используемые в LIMIS:
     * - $_SESSION['active_role'] (имя поля из формы выбора аккаунта LIMIS)
     * - $_SESSION['role']
     * - $_SESSION['user']['role']
     * - $_SESSION['user_role']
     *
     * @return string|null Имя активной роли либо null, если пользователь не авторизован
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
     *
     * @return bool true, если активная роль является Peakasutaja
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
     *
     * @return bool
     */
    public static function isAuthenticated(): bool
    {
        return self::getActiveRole() !== null;
    }

    /**
     * Режим аутентификации:
     * - 'standalone' (по умолчанию): автономный режим с отдельным паролем (admin_hash)
     * - 'limis': штатный режим LIMIS (проверка роли в существующей сессии LIMIS)
     *
     * @return string 'standalone' или 'limis'
     */
    public static function getAuthMode(): string
    {
        $mode = cfg()['auth_mode'] ?? 'standalone';
        return in_array($mode, ['limis', 'standalone'], true) ? $mode : 'standalone';
    }

    /**
     * Установить роль в сессию (для автономного входа и тестирования).
     *
     * @param string $role
     * @return void
     */
    public static function setSessionRole(string $role): void
    {
        $_SESSION['role'] = $role;
        $_SESSION['active_role'] = $role;
    }

    /**
     * Сбросить роль и завершить сессию.
     *
     * @return void
     */
    public static function logout(): void
    {
        unset($_SESSION['role'], $_SESSION['active_role'], $_SESSION['user']);
    }
}
