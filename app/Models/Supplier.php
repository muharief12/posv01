<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'suppliers';
    protected $guarded = ['id'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id', 'id');
    }

    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class, 'regency_id', 'id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id', 'id');
    }
}
