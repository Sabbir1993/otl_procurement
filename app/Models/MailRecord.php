<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailRecord extends Model
{
    protected $table = 'mails';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
    protected $keyType = 'string';
}
