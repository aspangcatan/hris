<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessInterest extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'business_interests';

    public $timestamps = false;
}
