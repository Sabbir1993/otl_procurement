<?php

namespace App\Domain;

/**
 * Supplier quotation parameter dictionary + pricing / terms maths (prototype FDEF, fReq, tierBase, unitPriceFor,
 * termsScore, bankCost, advExposure, cashOut). Field list: config/quote_fields.php.
 *
 * $F is the form data shape used everywhere: ['h' => header fields, 'l' => [pid => line fields]].
 */
class QuoteSpec
{
    public static function fields(): array
    {
        return config('quote_fields.fields');
    }

    public static function fApplies(array $f, string $pid): bool
    {
        return empty($f['types']) || in_array(Masters::material($pid)['type'], $f['types'], true);
    }

    /** Coded rules for fields flagged rule=true in the dictionary. */
    public static function rule(string $key, array $F, ?string $pid): bool
    {
        $h = $F['h'] ?? [];
        return match ($key) {
            'advPct' => (bool) preg_match('/advance/i', $h['payMethod'] ?? ''),
            'creditDays' => (bool) preg_match('/usance|credit/i', $h['payMethod'] ?? ''),
            'awb' => (bool) (($F['l'][$pid] ?? [])['sampleDate'] ?? ''),
            'exceptions' => (bool) preg_match('/except/i', $h['compliance'] ?? ''),
            default => false,
        };
    }

    /** Is field $f required for $pid? $rfqReq = RFQ-specific extra required groups (case rfq.reqFields). */
    public static function fReq(array $rfqReq, array $f, ?string $pid, array $F): bool
    {
        if ($f['s'] === 'l' && !self::fApplies($f, $pid)) {
            return false;
        }
        if (in_array($f['req'], ['R', 'Rw'], true)) {
            return true;
        }
        if (!empty($f['rule']) && self::rule($f['k'], $F, $pid)) {
            return true;
        }
        return in_array($f['grp'] ?? $f['k'], $rfqReq, true);
    }

    public static function fGet(array $F, array $f, ?string $pid)
    {
        return $f['s'] === 'h' ? ($F['h'][$f['k']] ?? null) : (($F['l'][$pid] ?? [])[$f['k']] ?? null);
    }

    public static function hasVal($v): bool
    {
        return is_array($v) ? count($v) > 0 : trim((string) ($v ?? '')) !== '';
    }

    /** Price for a quantity: the highest qualifying tier, else list price (or price). */
    public static function tierBase(array $q, $qty): float
    {
        $tiers = array_filter($q['tiers'] ?? [], fn ($t) => (float) $t['min'] > 0 && (float) $t['price'] > 0);
        usort($tiers, fn ($a, $b) => $b['min'] <=> $a['min']);
        foreach ($tiers as $t) {
            if ($qty >= (float) $t['min']) {
                return (float) $t['price'];
            }
        }
        return (float) ($q['listPrice'] ?? $q['price']);
    }

    public static function unitPriceFor(array $q, $qty): float
    {
        return round(self::tierBase($q, $qty) * (1 - ((float) ($q['disc'] ?? 0)) / 100), 4);
    }

    public static function payText(array $h): string
    {
        return trim(($h['payMethod'] ?? '') . (!empty($h['payDetail']) ? ' — ' . $h['payDetail'] : ''));
    }

    public static function termsScore(array $h): int
    {
        $m = $h['payMethod'] ?? '';
        if (preg_match('/at sight/i', $m)) {
            return 85;
        }
        if (preg_match('/usance/i', $m)) {
            return (int) floor(70 + min((float) ($h['creditDays'] ?? 0), 90) / 9 + 0.5);
        }
        if (preg_match('/credit/i', $m)) {
            return 78;
        }
        if (preg_match('/balance/i', $m)) {
            return (int) floor(62 - ((float) ($h['advPct'] ?? 0)) / 10 + 0.5);
        }
        if (preg_match('/advance/i', $m)) {
            return 40;
        }
        return 60;
    }

    public static function bankCost(array $h): int
    {
        $b = $h['bankCh'] ?? '';
        return preg_match('/Applicant/i', $b) ? 185 : (preg_match('/Shared/i', $b) ? 93 : (preg_match('/Beneficiary/i', $b) ? 0 : 185));
    }

    public static function advExposure(array $q, array $h): int
    {
        return preg_match('/advance/i', $h['payMethod'] ?? '') ? (int) floor((float) ($h['advPct'] ?? 0) / 100 * $q['price'] * $q['qty'] + 0.5) : 0;
    }

    public static function cashOut(array $h): string
    {
        $m = $h['payMethod'] ?? '';
        if (preg_match('/at sight/i', $m)) {
            return 'At document presentation';
        }
        if (preg_match('/usance|credit/i', $m)) {
            return ($h['creditDays'] ?: '?') . ' days after shipment';
        }
        if (preg_match('/balance/i', $m)) {
            return ($h['advPct'] ?: '?') . '% advance, rest against BL';
        }
        if (preg_match('/advance/i', $m)) {
            return ($h['advPct'] ?: 100) . '% before shipment';
        }
        return '—';
    }
}
