<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'sender_id',
        'receiver_id',
        'amount',
        'currency',
        'exchange_rate',
        'settlement_date',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'settlement_date' => 'date:Y-m-d',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
