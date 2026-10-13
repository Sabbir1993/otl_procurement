<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'app_notifications';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'read' => 'boolean',
    ];
}
