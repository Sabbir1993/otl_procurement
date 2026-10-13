<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Domain\Fmt;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\PurchaseCase;
use App\Models\Sequence;

/** Purchase-case lifecycle (prototype ACT.caseSave). */
class CaseService
{
    public const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** @param array{name:string,month:string,notes?:?string} $d  month like "Dec 2026" */
    public function create(array $d): CaseBook
    {
        Perm::need('case.new');
        $name = trim($d['name'] ?? '');
        if ($name === '') {
            throw new BusinessRule('Case name is required.');
        }
        [$mn, $yr] = array_pad(explode(' ', $d['month'] ?? ''), 2, '');
        $m = array_search(substr($mn, 0, 3), self::MONTHS, true);
        if ($m === false || !preg_match('/^\d{4}$/', $yr)) {
            throw new BusinessRule('Choose a valid booking month.');
        }
        $mon = strtoupper(substr($mn, 0, 3));
        $base = "IC-{$yr}-" . Fmt::pad($m + 1);
        $n = PurchaseCase::where('id', 'like', "IC-{$yr}-%")->count() + 1;
        $id = PurchaseCase::whereKey($base)->exists() ? "{$base}-{$n}" : $base;

        $t0 = Clock::ms();
        $model = PurchaseCase::create(['id' => $id, 'name' => $name, 'month' => $d['month'], 'mon' => $mon, 't0_ms' => $t0, 'created_by' => Perm::user(),
            'state' => CaseBook::blankState($t0, $d['notes'] ?? '')]);
        Sequence::firstOrCreate(['key' => 'IMPBOK-' . $mon], ['value' => 0]);
        Workflow::log('Case created', $id, $name, ['cid' => $id]);
        return new CaseBook($model);
    }
}
