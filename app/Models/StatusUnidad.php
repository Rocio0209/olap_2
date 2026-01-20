<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusUnidad extends Model
{
    use HasFactory;

    protected $table = 'status_unidades';

    protected $fillable = [
        'idstatus_unidad', 'status_unidad'
    ];

    protected $primaryKey = 'idstatus_unidad';

    public $timestamps = false;
}
