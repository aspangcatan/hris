<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalOrder extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'hospital_orders';

    protected $fillable = ['hospital_order', 'start_date', 'end_date', 'userid', 'created_by'];
}
