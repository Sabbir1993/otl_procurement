<?php

namespace App\Domain;

use App\Models\Calendar;
use App\Models\Material;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierProduct;

/** Request-cached access to master data (prototype prod(), supOf(), spName(), DB.cal, DB.settings). */
class Masters
{
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function materials(): array   // id => array
    {
        return self::$cache['mat'] ??= Material::orderBy('id')->get()->keyBy('id')->map->toArray()->all();
    }

    public static function material(string $pid): array
    {
        return self::materials()[$pid] ?? ['id' => $pid, 'name' => $pid, 'meta' => '', 'comp' => '', 'uom' => '', 'type' => 'fabric'];
    }

    public static function suppliers(): array   // id => array (email_verified exposed as emailVerified too)
    {
        return self::$cache['sup'] ??= Supplier::orderBy('id')->get()->keyBy('id')->map(function ($s) {
            $a = $s->toArray();
            $a['emailVerified'] = (bool) $s->email_verified;
            return $a;
        })->all();
    }

    public static function supplier(string $sid): array
    {
        return self::suppliers()[$sid] ?? ['id' => $sid, 'name' => $sid, 'tag' => '?'];
    }

    public static function supplierProducts(): array  // list of [sid, pid, name, active]
    {
        return self::$cache['sp'] ??= SupplierProduct::orderBy('id')->get()->map(fn ($p) => ['id' => $p->id, 'sid' => $p->supplier_id, 'pid' => $p->material_id, 'name' => $p->name, 'active' => (bool) $p->active])->all();
    }

    public static function spName(string $sid, string $pid): string
    {
        foreach (self::supplierProducts() as $p) {
            if ($p['sid'] === $sid && $p['pid'] === $pid) {
                return $p['name'];
            }
        }
        return '—';
    }

    public static function calendars(): array   // key => array
    {
        return self::$cache['cal'] ??= Calendar::all()->keyBy('key')->map->toArray()->all();
    }

    public static function calendar(string $key): ?array
    {
        return self::calendars()[$key] ?? null;
    }

    /** settings | flags | authority | reasons */
    public static function setting(string $key, $default = null)
    {
        return (self::$cache['set'] ??= Setting::all()->keyBy('key')->map(fn ($s) => $s->value)->all())[$key] ?? $default;
    }

    public static function putSetting(string $key, array $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        unset(self::$cache['set']);
    }
}
