<?php

namespace App\Enums;

enum ProductionType: string
{
    case Cutting = 'cutting';             // برش‌کاری
    case Coloring = 'coloring';           // رنگ‌کاری
    case Molding = 'molding';             // اتوکاری
    case Assembling = 'assembling';    // مونتاژ‌کاری

    /**
     * Get the Persian label for the inventory type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cutting => 'برش‌کاری',
            self::Coloring => 'رنگ‌کاری',
            self::Molding => 'اتو‌کاری',
            self::Assembling => 'مونتاژ‌کاری',
        };
    }

    /**
     * Get all inventory type values for validation.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
