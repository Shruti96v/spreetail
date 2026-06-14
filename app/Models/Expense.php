<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'paid_by',
        'description',
        'amount',
        'currency',
        'exchange_rate',
        'expense_date',
        'split_type',
        'is_settlement',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'expense_date' => 'date:Y-m-d',
        'is_settlement' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function splits()
    {
        return $this->hasMany(ExpenseSplit::class);
    }
}
