<?php

namespace App\Exceptions;

use App\Domain\Perm;
use RuntimeException;

/** Raised by Perm::need(); rendered by the handler as a warning toast + redirect back (never a bare 403 page). */
class SegregationOfDuties extends RuntimeException
{
    public function __construct(public string $permission)
    {
        $who = collect(Perm::who($permission))->map(fn ($r) => Perm::roleName($r))->implode(' or ') ?: '—';
        parent::__construct(Perm::roleName() . " can't do this (segregation of duties). Needs: $who.");
    }
}
