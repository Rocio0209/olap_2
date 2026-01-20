<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    use HasFactory;

    protected $fillable = [
        'idestado', 'estado', 'siglas'
    ];

    protected $primaryKey = 'idestado';

    public $timestamps = false;
}
