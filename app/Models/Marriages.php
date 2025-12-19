<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marriages extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'marriages';

    protected $fillable = [
        'userid',
        'spouse'
    ];

}
