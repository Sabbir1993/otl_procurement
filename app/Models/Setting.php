<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'value' => 'array',
    ];
}
