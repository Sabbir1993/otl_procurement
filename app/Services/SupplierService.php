<?php

namespace App\Services;

use App\Domain\Masters;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/** Supplier master (prototype ACT.supSave / supVerifySave). The master is controlled: identity/contact changes need callback verification. */
class SupplierService
{
    // ---- reads (controllers/views go through the service so storage can be swapped later) ----
    public function all(): array
    {
        return array_values(Masters::suppliers());
    }

    public function find(string $id): ?array
    {
        return Masters::suppliers()[$id] ?? null;
    }

    /** Supplier-product mappings (optionally for one supplier), each with the material name resolved as 'mname'. */
    public function products(?string $sid = null): array
    {
        return array_values(array_map(
            fn ($p) => $p + ['mname' => Masters::material($p['pid'])['name']],
            array_filter(Masters::supplierProducts(), fn ($p) => $sid === null || $p['sid'] === $sid)
        ));
    }

    /** a***@domain (prototype mask) */
    public static function maskEmail(?string $e): string
    {
        return preg_replace('/^(.).*@/', '$1***@', (string) $e);
    }

    /** Phone / address are sensitive: keep only the last 2 characters. */
    public static function mask(?string $v): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return '—';
        }
        return mb_strlen($v) <= 4 ? '••••' : str_repeat('•', min(10, mb_strlen($v) - 2)) . mb_substr($v, -2);
    }

    /** @param array{id?:?string,name:string,cc:string,cal:string,contact:string,desig?:?string,address?:?string,email:string,phone?:?string,status:string} $d */
    public function save(array $d): Supplier
    {
        Perm::need('sup.edit');
        foreach (['name', 'cc', 'contact', 'email'] as $k) {
            if (trim((string) ($d[$k] ?? '')) === '') {
                throw new BusinessRule('Supplier ' . $k . ' is required.');
            }
        }
        if (!isset(Masters::calendars()[$d['cal'] ?? ''])) {
            throw new BusinessRule('Choose an existing working-day calendar.');
        }
        if (!in_array($d['status'] ?? '', ['Active', 'Inactive', 'Blocked'], true)) {
            throw new BusinessRule('Invalid supplier status.');
        }
        $f = [
            'name' => trim($d['name']), 'cc' => strtoupper(trim($d['cc'])), 'cal' => $d['cal'], 'contact' => trim($d['contact']),
            'desig' => trim((string) ($d['desig'] ?? '')), 'email' => trim($d['email']), 'phone' => trim((string) ($d['phone'] ?? '')),
            'address' => trim((string) ($d['address'] ?? '')), 'status' => $d['status'],
        ];

        return DB::transaction(function () use ($d, $f) {
            if (!empty($d['id'])) {
                $s = Supplier::findOrFail($d['id']);
                $changed = array_keys(array_filter($f, fn ($v, $k) => (string) $s->{$k} !== (string) $v, ARRAY_FILTER_USE_BOTH));
                $s->fill($f);
                $identity = array_intersect($changed, ['name', 'cc']);
                $contact = array_intersect($changed, ['email', 'contact', 'phone']);
                if ($identity || $contact) {
                    $s->email_verified = false;      // controlled master: needs an independent callback again
                }
                $s->save();
                if ($identity) {
                    Workflow::raise('Supplier identity changed', $s->id, "{$s->name} legal name / country changed — callback verification needed");
                }
                if ($contact) {
                    Workflow::raise('Supplier contact changed', $s->id, "{$s->name} contact details changed (" . implode(', ', $contact) . ') — callback verification needed');
                }
                Workflow::log('Supplier saved', $s->id, $s->name . ($changed ? ' · changed: ' . implode(', ', $changed) : ''));
            } else {
                $n = (int) Supplier::get()->map(fn ($x) => (int) substr($x->id, 4))->max() + 1;
                $s = Supplier::create($f + [
                    'id' => 'SUP-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT), 'tag' => $this->tag($n),
                    'email_verified' => false, 'since' => '2026',
                ]);
                Workflow::log('Supplier saved', $s->id, $s->name . ' · created');
            }
            Masters::flush();
            return $s;
        });
    }

    private function tag(int $n): string
    {
        return $n <= 26 ? chr(64 + $n) : chr(64 + intdiv($n - 1, 26)) . chr(65 + ($n - 1) % 26);
    }

    /** Independent callback verification: marks the contact verified and resolves the open change exceptions. */
    public function verify(string $id, string $ref): Supplier
    {
        Perm::need('po.verify');
        $ref = trim($ref);
        if ($ref === '') {
            throw new BusinessRule('Enter the callback reference / call log ID.');
        }
        return DB::transaction(function () use ($id, $ref) {
            $s = Supplier::findOrFail($id);
            if ($s->email_verified) {
                throw new BusinessRule('This supplier is already verified.');
            }
            $s->email_verified = true;
            $s->save();
            foreach (['Supplier contact changed', 'Supplier identity changed'] as $t) {
                Workflow::resolve($t, $s->id, 'Callback verified ' . $ref);
            }
            Workflow::log('Supplier contact verified', $s->id, 'Callback ' . $ref);
            Masters::flush();
            return $s;
        });
    }
}
