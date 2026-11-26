<?php

namespace App\Enums;

/**
 * Role of a user inside an institution (a non-personal team).
 */
enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Owners and admins run the institution; members only start games in the app.
     */
    public function isDirector(): bool
    {
        return $this !== self::Member;
    }

    /**
     * The role name used by the mobile API.
     */
    public function apiName(): string
    {
        return $this->isDirector() ? 'director' : 'attendant';
    }

    public function label(): string
    {
        return $this->isDirector() ? 'Director' : 'Attendant';
    }

    /**
     * @return array<int, string>
     */
    public static function directorValues(): array
    {
        return [self::Owner->value, self::Admin->value];
    }
}
