<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalOrder extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'hospital_orders';

    protected $fillable = ['order_number', 'order_date', 'time_from', 'time_to', 'start_date', 'end_date', 'created_by'];
}
