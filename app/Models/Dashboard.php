<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Hardcoded sample data. Swap these method bodies for DB queries — the views
 * and controllers only depend on the array shapes returned here.
 */
final class Dashboard
{
    public static function stats(): array
    {
        return [
            ['key' => 'stat.revenue',      'value' => '$128,430', 'icon' => 'bi-currency-dollar', 'tone' => 'primary', 'trend' => 'success', 'dir' => 'up',   'delta' => '12.4%'],
            ['key' => 'stat.active_users', 'value' => '8,940',    'icon' => 'bi-people-fill',     'tone' => 'info',    'trend' => 'success', 'dir' => 'up',   'delta' => '4.1%'],
            ['key' => 'stat.orders',       'value' => '1,284',    'icon' => 'bi-bag-check-fill',  'tone' => 'warning', 'trend' => 'danger',  'dir' => 'down', 'delta' => '2.3%'],
            ['key' => 'stat.conversion',   'value' => '3.8%',     'icon' => 'bi-graph-up-arrow',  'tone' => 'primary', 'trend' => 'success', 'dir' => 'up',   'delta' => '0.6%'],
        ];
    }

    public static function revenueSeries(): array
    {
        return [12000, 15400, 13200, 18100, 16700, 20300, 19200];
    }

    public static function traffic(): array
    {
        return [
            ['key' => 'traffic.organic',  'color' => '#4f46e5', 'pct' => 48],
            ['key' => 'traffic.social',   'color' => '#22c55e', 'pct' => 27],
            ['key' => 'traffic.referral', 'color' => '#f59e0b', 'pct' => 17],
            ['key' => 'traffic.other',    'color' => '#e2e8f0', 'pct' => 8],
        ];
    }

    public static function orders(): array
    {
        return [
            ['name' => 'Nini Kapanadze',  'initials' => 'NK', 'color' => '#6366f1', 'product' => 'UI Kit Pro',       'date' => '2026-07-24', 'amount' => '$249.00', 'tone' => 'success', 'status' => 'status.paid'],
            ['name' => 'Luka Gelashvili', 'initials' => 'LG', 'color' => '#22c55e', 'product' => 'Analytics Add-on', 'date' => '2026-07-23', 'amount' => '$89.00',  'tone' => 'warning', 'status' => 'status.pending'],
            ['name' => 'Ana Chkheidze',   'initials' => 'AC', 'color' => '#f59e0b', 'product' => 'Team Seats (5)',   'date' => '2026-07-22', 'amount' => '$620.00', 'tone' => 'success', 'status' => 'status.paid'],
            ['name' => 'Beka Lomidze',    'initials' => 'BL', 'color' => '#ef4444', 'product' => 'Storage Upgrade',  'date' => '2026-07-21', 'amount' => '$35.00',  'tone' => 'danger',  'status' => 'status.rejected'],
            ['name' => 'Tako Maisuradze', 'initials' => 'TM', 'color' => '#4f46e5', 'product' => 'API Access',       'date' => '2026-07-20', 'amount' => '$149.00', 'tone' => 'success', 'status' => 'status.paid'],
        ];
    }

    public static function activity(): array
    {
        return [
            ['text' => 'activity.1', 'time' => 'activity.1_time'],
            ['text' => 'activity.2', 'time' => 'activity.2_time'],
            ['text' => 'activity.3', 'time' => 'activity.3_time'],
            ['text' => 'activity.4', 'time' => 'activity.4_time'],
        ];
    }

    public static function goal(): array
    {
        return ['current' => '$18,200', 'target' => '$25,000', 'percent' => 73];
    }
}
