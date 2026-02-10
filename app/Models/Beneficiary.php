<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiary extends Model
{
    use CrudTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'beneficiaries';
    protected $guarded = ['id'];

    protected $casts = [
        'birthday' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute()
    {
        return trim("{$this->last_name}, {$this->first_name} {$this->middle_name} {$this->extension_name}");
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function import()
    {
        return $this->belongsTo(Import::class);
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
