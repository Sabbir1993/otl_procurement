<?php

namespace App\Support;

use App\Domain\CaseBook;
use App\Models\PurchaseCase;

/** The "working case" selected in the top bar (kept in the session). */
class WorkingCase
{
    public static function id(): ?string
    {
        $id = session('case_id');
        if ($id && PurchaseCase::whereKey($id)->exists()) {
            return $id;
        }
        $id = PurchaseCase::whereKey(config('oilpms.default_case'))->value('id') ?? PurchaseCase::orderBy('id')->value('id');
        if ($id) {
            session(['case_id' => $id]);
        }
        return $id;
    }

    public static function get(): ?CaseBook
    {
        $id = self::id();
        return $id ? CaseBook::find($id) : null;
    }

    public static function set(string $id): void
    {
        if (PurchaseCase::whereKey($id)->exists()) {
            session(['case_id' => $id]);
        }
    }
}
