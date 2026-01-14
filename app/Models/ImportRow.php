<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    protected $fillable = [
        'import_id',
        'FirstName','LastName','email_address','Password','UnitPath',
        'personalEmail','studentPhone','Title','studentDepartment','DepartmentName','ChangePassNext',
        'first_name','middle_name','last_name',
        'generated_email','email_generation_attempts','email_status','email_error',
    ];

    public function import() { return $this->belongsTo(Import::class); }
}
