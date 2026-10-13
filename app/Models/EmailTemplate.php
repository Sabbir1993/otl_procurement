<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';
    protected $primaryKey = 'code';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';
}
