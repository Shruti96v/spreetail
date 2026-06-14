<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'requester_id',
        'type',
        'target_type',
        'target_id',
        'proposed_data',
        'status',
    ];

    protected $casts = [
        'proposed_data' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
