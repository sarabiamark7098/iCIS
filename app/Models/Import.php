<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Import extends Model
{
    use CrudTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'imports';
    protected $guarded = ['id'];

    protected $casts = [
        'archived_at' => 'datetime',
        'payout_schedule_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /*
    |--------------------------------------------------------------------------
    | METHODS
    |--------------------------------------------------------------------------
    */

    public function archive()
    {
        $this->update(['archived_at' => now()]);
    }

    public function unarchive()
    {
        $this->update(['archived_at' => null]);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function profiles()
    {
        return $this->hasMany(Profile::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
