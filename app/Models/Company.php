<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'gst_number',
        'pan_number',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'is_default',
    ];
}
