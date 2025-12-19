<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpouseLiabilities extends Model
{
    use HasFactory;
       protected $connection = 'mysql';
    protected $table = 'spouse_liabilities';

    public $timestamps = false;
}
