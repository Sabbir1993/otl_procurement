<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyItem extends Model
{
    protected $table = 'policy_items';
    protected $primaryKey = 'n';
    public $timestamps = false;
    protected $guarded = [];

    public $incrementing = false;
}
