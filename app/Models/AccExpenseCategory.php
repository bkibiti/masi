<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccExpenseCategory extends Model
{
    protected $table = 'expense_categories';
    public $timestamps = false;


    public function expense()
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }

}
