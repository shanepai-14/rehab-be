<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'last_name',
        'first_name', 
        'middle_initial',
        'sex',
        'birth_date',
        'address',
        'contact_number',
        'province',
        'district',
        'email',
        'password',
        'role',
        'is_verified',
        'created_by'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'is_verified' => 'boolean'
        ];
    }

    // Role constants
    const ROLE_PATIENT = 'patient';
    const ROLE_DOCTOR = 'doctor'; 
    const ROLE_ADMIN = 'admin';

    public function isPatient()
    {
        return $this->role === self::ROLE_PATIENT;
    }

    public function isDoctor() 
    {
        return $this->role === self::ROLE_DOCTOR;
    }

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->middle_initial . ' ' . $this->last_name);
    }

        public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }
}