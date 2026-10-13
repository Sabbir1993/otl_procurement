<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    protected $table = 'calendars';
    protected $primaryKey = 'key';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'years' => 'array',
        'holidays' => 'array',
        'makeup' => 'array',
        'approved' => 'boolean',
    ];
}
