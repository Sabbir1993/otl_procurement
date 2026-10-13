<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $table = 'sequences';
    protected $primaryKey = 'key';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';
}
