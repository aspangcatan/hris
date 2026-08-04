<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassSlip extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'pass_slips';

    protected $fillable = [
        'user_id',
        'slip_date',
        'time_out',
        'time_in',
        'type',
        'status',
        'approver_id',
        'approved_at',
        'filed_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
}
