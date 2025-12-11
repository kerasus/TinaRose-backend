<?php

namespace App\Enums;

enum UserRoleType: string
{
    case Manager = 'Manager';
    case Accountant = 'Accountant';
    case ProductManager = 'ProductManager';
    case WarehouseKeeper = 'WarehouseKeeper';
    case MiddleWorker = 'MiddleWorker';
    case Assembler = 'Assembler';
    case FabricCutter = 'FabricCutter';
    case ColoringWorker = 'ColoringWorker';
    case MoldingWorker = 'MoldingWorker';
    case Customer = 'Customer';

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
            self::ProductManager => 'مدیر محصول',
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
