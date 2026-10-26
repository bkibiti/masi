<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expenses';

    public $timestamps = false;

    public function category()
    {
        return $this->belongsTo(AccExpenseCategory::class, 'expense_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Expenses the user may see: all of them with "View Advanced Dashboard", otherwise only the ones they recorded.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return $user->can('View Advanced Dashboard')
            ? $query
            : $query->where('updated_by', $user->id);
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->can('View Advanced Dashboard') || (int) $this->updated_by === (int) $user->id;
    }
}
