<?php

namespace App\Http\Controllers;

use App\Models\AccExpenseCategory;
use Exception;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{

    public function index()
    {
        $expense_categories = AccExpenseCategory::orderBy('id', 'ASC')->get();
        return view('masters.expense_category.index', compact("expense_categories"));

    }

    public function store(Request $request)
    {

        $expense_category = new AccExpenseCategory;
        $expense_category->name = $request->name;
        $expense_category->save();

        session()->flash("alert-success", "Expense category added successfully!");
        return back();
    }


    public function update(Request $request)
    {
        $expense_category = AccExpenseCategory::find($request->id);
        $expense_category->name = $request->name;
        $expense_category->save();
        session()->flash("alert-success", "Expense category updated successfully!");
        return back();
    }


    public function destroy(Request $request)
    {

        try {
            AccExpenseCategory::find($request->id)->delete();
            session()->flash("alert-danger", "Expense category deleted Successfully!");
            return back();
        } catch (Exception $e) {
            session()->flash("alert-danger", "Expense category in use");
            return back();
        }

    }
}
