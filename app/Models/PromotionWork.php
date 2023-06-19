<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionWork extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'promotion_work_experience';

    public $timestamps = false;
}
