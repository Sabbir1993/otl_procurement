<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcceptanceTest extends Model
{
    protected $table = 'acceptance_tests';
    protected $primaryKey = 'code';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';
}
