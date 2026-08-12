<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnterpriseInquiry extends Model
{
    protected $fillable = ['user_id', 'about', 'email', 'phone', 'country', 'employee_count', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
