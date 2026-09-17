<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Roles that can manage company resources (users, subscription).
     */
    public function canManage(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
