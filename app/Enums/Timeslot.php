<?php

namespace App\Enums;

enum Timeslot: string
{
    case Morning = '08:00-12:00';
    case Afternoon = '12:00-16:00';
    case Evening = '16:00-20:00';

    /**
     * Human label key for translation.
     */
    public function label(): string
    {
        return match ($this) {
            self::Morning => 'delivery.timeslot.morning',
            self::Afternoon => 'delivery.timeslot.afternoon',
            self::Evening => 'delivery.timeslot.evening',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
