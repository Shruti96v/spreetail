<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
                    ->withPivot('joined_at', 'left_at')
                    ->withTimestamps();
    }

    public function groupMemberships()
    {
        return $this->hasMany(GroupMember::class);
    }

    public function expensesPaid()
    {
        return $this->hasMany(Expense::class, 'paid_by');
    }

    public function splits()
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    public function settlementsSent()
    {
        return $this->hasMany(Settlement::class, 'sender_id');
    }

    public function settlementsReceived()
    {
        return $this->hasMany(Settlement::class, 'receiver_id');
    }
}
