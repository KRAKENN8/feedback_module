<?php

declare(strict_types=1);

/**
 * Валидатор и нормализатор входящих данных отзыва жителей.
 *
 * Обеспечивает:
 * - Проверку обязательных полей (lang, purpose, completed, ease_rating).
 * - Контроль допустимых значений против списков ENUMS.
 * - Ограничение длины комментария (до 500 символов) и фильтрацию непечатных управляющих символов.
 * - Нормализацию пустых опциональных строк в null.
 */
class FeedbackValidator
{
    /**
     * Валидирует и нормализует входящие поля формы.
     *
     * @param array<string, mixed> $in Необработанные входные данные от клиента
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     data?: array{
     *         lang: string,
     *         purpose: string,
     *         completed: string,
     *         ease_rating: int,
     *         difficulty: ?string,
     *         comment: ?string,
     *         page_group: ?string
     *     }
     * } Результат валидации с нормализованными данными или кодом ошибки
     */
    public static function validate(array $in): array
    {
        $lang = $in['lang'] ?? null;
        $purpose = $in['purpose'] ?? null;
        $completed = $in['completed'] ?? null;
        $rating = $in['ease_rating'] ?? null;
        $diff = $in['difficulty'] ?? null;
        $pg = $in['page_group'] ?? null;
        $com = $in['comment'] ?? null;

        // Проверка обязательных полей и их типов
        if (
            !in_array($lang, ENUMS['lang'], true)
            || !in_array($purpose, ENUMS['purpose'], true)
            || !in_array($completed, ENUMS['completed'], true)
            || !is_int($rating)
            || $rating < 1
            || $rating > 5
        ) {
            return ['ok' => false, 'error' => 'validation'];
        }

        // Проверка опционального поля сложности
        if ($diff !== null && $diff !== '' && !in_array($diff, ENUMS['difficulty'], true)) {
            return ['ok' => false, 'error' => 'validation'];
        }

        // Проверка опциональной категории страницы
        if ($pg !== null && $pg !== '' && !in_array($pg, ENUMS['page_group'], true)) {
            return ['ok' => false, 'error' => 'validation'];
        }

        // Проверка типа комментария и валидности UTF-8
        if ($com !== null) {
            if (!is_string($com)) {
                return ['ok' => false, 'error' => 'validation'];
            }
            $isUtf8 = function_exists('mb_check_encoding')
                ? mb_check_encoding($com, 'UTF-8')
                : (preg_match('//u', $com) === 1);
            if (!$isUtf8) {
                return ['ok' => false, 'error' => 'validation'];
            }
        }

        // Очистка от управляющих символов и проверка длины (максимум 500 символов)
        if ($com !== null) {
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $com);
            $com = $cleaned === null ? null : trim($cleaned);
            if ($com !== null && $com !== '') {
                $charCount = function_exists('mb_strlen')
                    ? mb_strlen($com, 'UTF-8')
                    : (preg_match_all('/./u', $com) ?: strlen($com));
                if ($charCount > 500) {
                    return ['ok' => false, 'error' => 'too_long'];
                }
            } else {
                $com = null;
            }
        }

        return [
            'ok' => true,
            'data' => [
                'lang' => $lang,
                'purpose' => $purpose,
                'completed' => $completed,
                'ease_rating' => $rating,
                'difficulty' => ($diff === '' ? null : $diff),
                'comment' => ($com === '' ? null : $com),
                'page_group' => ($pg === '' ? null : $pg),
            ],
        ];
    }
}
