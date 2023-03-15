<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRecords extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'service_records';
    public $timestamps = false;
}
