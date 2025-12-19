<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpouseBusinessInterest extends Model
{
    use HasFactory;
     protected $connection = 'mysql';
    protected $table = 'spouse_business_interests';

    public $timestamps = false;
}
