<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'terms' => 'array',
        'email_verified' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(SupplierProduct::class);
    }
}
