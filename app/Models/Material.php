<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $table = 'materials';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';
}
