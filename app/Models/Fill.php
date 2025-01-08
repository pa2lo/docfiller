<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fill extends Model
{
    protected $guarded = [];

	protected $casts = [
		'addressData' => 'json',
		'batch' => 'array'
    ];

	public function documents() {
		return $this->belongsTo(Document::class);
	}

	public function user() {
		return $this->belongsTo(User::class);
	}

	public function scopeFilter($query, $filters = []) {
		if(!$filters) return $query;

		$query->when($filters['title'] ?? null, function ($query, $title) {
			$query->where(function ($query) use ($title) {
				$query->where('addressData->firma', 'like', "%$title%")
					  ->orWhere('addressData->ico', 'like', "%$title%")
					  ->orWhere('addressData->meno', 'like', "%$title%")
					  ->orWhere('addressData->priezvisko', 'like', "%$title%")
					  ->orWhere('note', 'like', "%$title%");
			});
		})->when($filters['document'] ?? null, function ($query, $document) {
			$query->where('document_id', $document);
        })->when($filters['by'] ?? null, function ($query, $by) {
			$query->where('user_id', $by);
        })->when($filters['from'] ?? null, function ($query, $from) {
			$query->where('created_at', ">", $from);
		})->when($filters['to'] ?? null, function ($query, $to) {
			$query->where('created_at', "<", $to);
		});
	}
}
