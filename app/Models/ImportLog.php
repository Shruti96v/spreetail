<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'status',
        'rows_processed',
    ];

    public function anomalies()
    {
        return $this->hasMany(Anomaly::class);
    }
}
