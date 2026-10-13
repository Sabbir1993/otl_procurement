<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseCase extends Model
{
    protected $table = 'purchase_cases';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'state' => 'array',
    ];
}
