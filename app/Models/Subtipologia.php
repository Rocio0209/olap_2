<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subtipologia extends Model
{
    use HasFactory;

    protected $table = 'subtipologias';

    protected $fillable = [
        'idsubtipologia', 'subtipologia','descripcion_subtipologia'
    ];

    protected $primaryKey = 'idsubtipologia';

    public $timestamps = false;

}
