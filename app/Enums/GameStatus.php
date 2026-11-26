<?php

namespace App\Enums;

enum GameStatus: string
{
    case Sent = 'sent';
    case Used = 'used';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Used => 'green',
            self::Sent => 'zinc',
            self::Failed => 'red',
            self::Expired => 'amber',
        };
    }
}
