<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCredits extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'leave_credits';

    public $timestamps = false;
}
