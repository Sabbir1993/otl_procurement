<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExceptionRecord extends Model
{
    protected $table = 'exceptions';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';
}
