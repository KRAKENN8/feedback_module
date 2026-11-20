<?php

declare(strict_types=1);

/**
 * Валидатор входящих данных отзыва жителей по ТЗ № LIMIS-FEEDBACK-01.
 */
class FeedbackValidator
{
    /**
     * Валидирует и нормализует входящие поля формы.
     *
     * @param array<string, mixed> $in
     * @return array{ok: bool, error?: string, data?: array{lang: string, purpose: string, completed: string, ease_rating: int, difficulty: ?string, comment: ?string, page_group: ?string}}
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

        if ($diff !== null && $diff !== '' && !in_array($diff, ENUMS['difficulty'], true)) {
            return ['ok' => false, 'error' => 'validation'];
        }

        if ($pg !== null && $pg !== '' && !in_array($pg, ENUMS['page_group'], true)) {
            return ['ok' => false, 'error' => 'validation'];
        }

        if ($com !== null && !is_string($com)) {
            return ['ok' => false, 'error' => 'validation'];
        }

        $com = $com === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $com));
        if ($com !== null && mb_strlen($com) > 500) {
            return ['ok' => false, 'error' => 'too_long'];
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
