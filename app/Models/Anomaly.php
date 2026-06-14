<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anomaly extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_log_id',
        'row_number',
        'raw_data',
        'anomaly_type',
        'severity',
        'description',
        'policy_applied',
        'status',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function importLog()
    {
        return $this->belongsTo(ImportLog::class);
    }
}
