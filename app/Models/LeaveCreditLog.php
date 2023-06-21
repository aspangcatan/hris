<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCreditLog extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'leave_credit_logs';

    public $timestamps = false;
}
