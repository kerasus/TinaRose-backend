<?php

namespace App\Enums;

enum UserRoleType: string
{
    case Customer = 'Customer';
    case MiddleWorker = 'MiddleWorker';
    case WarehouseKeeper = 'WarehouseKeeper';
    case Assembler = 'Assembler';
    case MoldingWorker = 'MoldingWorker';
    case ColoringWorker = 'ColoringWorker';
    case FabricCutter = 'FabricCutter';
    case Accountant = 'Accountant';
    case Manager = 'Manager';

    /**
     * Get the label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Customer => 'مشتری',
            self::MiddleWorker => 'وسط کار',
            self::WarehouseKeeper => 'انباردار',
            self::Assembler => 'مونتاژ کار',
            self::MoldingWorker => 'اتو کار',
            self::ColoringWorker => 'رنگ کار',
            self::FabricCutter => 'برش کار',
            self::Accountant => 'حسابدار',
            self::Manager => 'مدیر',
        };
    }

    /**
     * Get all role names for validation.
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
