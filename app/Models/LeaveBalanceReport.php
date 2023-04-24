<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalanceReport extends Model
{
    use HasFactory;

    protected $connection = 'hr';
    protected $table = 'leave_bal';
}
