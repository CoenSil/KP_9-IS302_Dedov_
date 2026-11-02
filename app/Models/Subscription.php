<?php

namespace App\Models;

/**
 * Подписка организации.
 *
 * Пока биллинг ручной: администратор платформы выставляет счёт и продлевает
 * период. Модель только читает состояние и лимиты — ничего не списывает.
 */
class Subscription
{
    public const TRIAL_DAYS = 14;

    public const PLANS = [
        'trial'  => 'Пробный период',
        'start'  => 'Старт',
        'school' => 'Школа',
        'network'=> 'Сеть',
        'legacy' => 'Без ограничений',
    ];

    /**
     * @return array{status:string, label:string, plan:string, days_left:?int, until:?string}
     */
    public static function state(?array $company): array
    {
        $plan  = (string) ($company['plan'] ?? '');
        $trial = self::toTs($company['trial_ends_at'] ?? null);
        $paid  = self::toTs($company['paid_until'] ?? null);
        $now   = time();

        if ($paid !== null && $paid >= $now) {
            return [
                'status'    => 'active',
                'label'     => 'Оплачен',
                'plan'      => $plan !== '' ? $plan : 'school',
                'days_left' => (int) ceil(($paid - $now) / 86400),
                'until'     => date('d.m.Y', $paid),
            ];
        }

        if ($trial !== null && $trial >= $now) {
            return [
                'status'    => 'trial',
                'label'     => 'Пробный период',
                'plan'      => $plan !== '' ? $plan : 'trial',
                'days_left' => (int) ceil(($trial - $now) / 86400),
                'until'     => date('d.m.Y', $trial),
            ];
        }

        if ($paid === null && $trial === null) {
            // Организации, созданные до появления подписок.
            return [
                'status'    => 'legacy',
                'label'     => 'Действует',
                'plan'      => $plan !== '' ? $plan : 'legacy',
                'days_left' => null,
                'until'     => null,
            ];
        }

        return [
            'status'    => 'expired',
            'label'     => 'Подписка истекла',
            'plan'      => $plan !== '' ? $plan : 'trial',
            'days_left' => 0,
            'until'     => $trial !== null ? date('d.m.Y', $trial) : date('d.m.Y', $paid),
        ];
    }

    public static function isExpired(?array $company): bool
    {
        return self::state($company)['status'] === 'expired';
    }

    /**
     * Лимит по тарифу. 0 — без ограничений.
     */
    public static function limit(?array $company, string $what): int
    {
        $key   = $what === 'students' ? 'max_students' : 'max_employees';
        $value = isset($company[$key]) && $company[$key] !== null ? (int) $company[$key] : 0;

        return max(0, $value);
    }

    public static function planLabel(?array $company): string
    {
        $plan = (string) ($company['plan'] ?? '');
        return self::PLANS[$plan] ?? ($plan !== '' ? $plan : 'Не указан');
    }

    private static function toTs($value): ?int
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts === false ? null : $ts;
    }
}
