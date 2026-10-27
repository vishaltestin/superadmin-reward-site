<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemoRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'company_name',
        'mobile',
        'department',
        'designation',
        'number_of_employee',
        'how_did_you_hear_about_us',
        'message',
        'status',
    ];
}