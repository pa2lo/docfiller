<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = [];

	protected $casts = [
		'deprecated' => 'boolean',
		'fields' => 'array'
    ];

	public function fills() {
		return $this->hasMany(Fill::class);
	}

	public function user() {
		return $this->belongsTo(User::class);
	}
}
