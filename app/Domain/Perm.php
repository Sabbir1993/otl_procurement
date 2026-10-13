<?php

namespace App\Domain;

use App\Exceptions\SegregationOfDuties;

/**
 * Roles & permissions (segregation of duties). Source of truth: config/roles.php (from the prototype PERM/ROLE_NAV).
 * The active role is the authenticated user's role (demo: re-login as another role via /demo/login).
 */
class Perm
{
    public static function role(): string
    {
        return auth()->user()->role ?? 'audit';
    }

    public static function roles(): array
    {
        return config('roles.roles');
    }

    public static function roleName(?string $role = null): string
    {
        return config('roles.roles.' . ($role ?? self::role()) . '.n', '');
    }

    /** Display name of the acting user (prototype user()). */
    public static function user(): string
    {
        return auth()->user()->name ?? config('roles.roles.' . self::role() . '.u', 'System');
    }

    public static function can(string $action, ?string $role = null): bool
    {
        return in_array($action, config('roles.perm.' . ($role ?? self::role()), []), true);
    }

    /** Roles allowed to perform $action. */
    public static function who(string $action): array
    {
        return array_keys(array_filter(config('roles.perm'), fn ($list) => in_array($action, $list, true)));
    }

    /** Throws SegregationOfDuties (rendered as a warning toast + redirect back) when the current role lacks $action. */
    public static function need(string $action): void
    {
        if (!self::can($action)) {
            throw new SegregationOfDuties($action);
        }
    }

    /** Is a menu page visible to the role? (ROLE_NAV). $group = group key, $page = page key. */
    public static function navAllows(string $group, string $page, ?string $role = null): bool
    {
        $m = config('roles.role_nav.' . ($role ?? self::role()), []);
        if (!isset($m[$group])) {
            return false;
        }
        return $m[$group] === true || in_array($page, (array) $m[$group], true);
    }
}
