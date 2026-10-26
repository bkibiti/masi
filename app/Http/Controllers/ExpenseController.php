<?php

namespace App\Http\Controllers;

use App\Models\AccExpenseCategory;
use App\Models\Expense;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    private const LOCAL_TIMEZONE = 'Africa/Dar_es_Salaam';

    /**
     * Expenses, filtered by date range (this month by default) and optionally by category.
     * Users without "View Advanced Dashboard" only see the expenses they recorded.
     */
    public function index(Request $request)
    {
        [$fromDate, $toDate] = $this->historyDateRange($request);

        $expense_category = AccExpenseCategory::all();
        $Expenses = Expense::visibleTo(Auth::user())
            ->with(['category', 'user'])
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->when($request->filled('expense_category') && $request->expense_category != 0, fn ($q) => $q->where('expense_category_id', $request->expense_category))
            ->get();

        $total = $Expenses->sum('amount');

        return view('expense.index', compact('expense_category', 'Expenses', 'total', 'fromDate', 'toDate'));
    }

    /**
     * Record expense screen: one expense at a time, plus the user's quick categories and expenses recorded today.
     */
    public function create()
    {
        $userId = Auth::id();
        $todayStart = CarbonImmutable::now(self::LOCAL_TIMEZONE)->startOfDay()->setTimezone('UTC');
        $todayEnd = $todayStart->addDay();

        $quickCategories = $this->quickCategories($userId, $todayStart);
        $quickCategoryIds = $quickCategories->pluck('id')->all();

        $allCategories = AccExpenseCategory::select('id', 'name')->orderBy('name')->get();

        $recentDescriptions = [];
        foreach ($quickCategoryIds as $categoryId) {
            $recentDescriptions[$categoryId] = Expense::where('updated_by', $userId)
                ->where('expense_category_id', $categoryId)
                ->where('expense_description', '!=', '')
                ->selectRaw('expense_description, MAX(created_at) as last_used')
                ->groupBy('expense_description')
                ->orderByDesc('last_used')
                ->limit(3)
                ->pluck('expense_description');
        }

        $todayExpenses = Expense::with('category')
            ->where('updated_by', $userId)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $todayCount = $todayExpenses->count();
        $todayTotal = $todayExpenses->sum('amount');

        $lastExpense = session('last_expense');
        if ($lastExpense) {
            $lastExpense['date'] = CarbonImmutable::parse($lastExpense['date'])->setTimezone(self::LOCAL_TIMEZONE);
        }
        $todayDate = CarbonImmutable::now(self::LOCAL_TIMEZONE)->toDateString();

        return view('expense.create', compact(
            'quickCategories',
            'allCategories',
            'recentDescriptions',
            'todayExpenses',
            'todayCount',
            'todayTotal',
            'lastExpense',
            'todayDate',
        ));
    }

    /**
     * The user's 6 most-used categories in the last 90 days, filled up with the overall
     * most-used categories, then alphabetically, until there are 6 (or all categories run out).
     */
    private function quickCategories(int $userId, CarbonImmutable $todayStart)
    {
        $since90Days = $todayStart->subDays(90);

        $quickCategoryIds = Expense::where('updated_by', $userId)
            ->where('created_at', '>=', $since90Days)
            ->select('expense_category_id')
            ->selectRaw('COUNT(*) as uses')
            ->groupBy('expense_category_id')
            ->orderByDesc('uses')
            ->limit(6)
            ->pluck('expense_category_id')
            ->all();

        if (count($quickCategoryIds) < 6) {
            $overallCategoryIds = Expense::select('expense_category_id')
                ->selectRaw('COUNT(*) as uses')
                ->groupBy('expense_category_id')
                ->orderByDesc('uses')
                ->pluck('expense_category_id')
                ->all();

            foreach ($overallCategoryIds as $categoryId) {
                if (count($quickCategoryIds) >= 6) {
                    break;
                }
                if (! in_array($categoryId, $quickCategoryIds, true)) {
                    $quickCategoryIds[] = $categoryId;
                }
            }
        }

        if (count($quickCategoryIds) < 6) {
            $alphabeticalIds = AccExpenseCategory::orderBy('name')->pluck('id')->all();

            foreach ($alphabeticalIds as $categoryId) {
                if (count($quickCategoryIds) >= 6) {
                    break;
                }
                if (! in_array($categoryId, $quickCategoryIds, true)) {
                    $quickCategoryIds[] = $categoryId;
                }
            }
        }

        return AccExpenseCategory::whereIn('id', $quickCategoryIds)
            ->get()
            ->sortBy(fn (AccExpenseCategory $category) => array_search($category->id, $quickCategoryIds))
            ->values();
    }

    /**
     * Save one expense. payment_method_id is left NULL; this app does not record it for expenses.
     */
    public function store(Request $request)
    {
        $request->merge(['amount' => str_replace(',', '', (string) $request->amount)]);

        $todayDate = CarbonImmutable::now(self::LOCAL_TIMEZONE)->toDateString();
        $minDate = CarbonImmutable::now(self::LOCAL_TIMEZONE)->subDays(31)->toDateString();

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_description' => 'required|max:200',
            'date' => 'required|date_format:Y-m-d|before_or_equal:'.$todayDate.'|after_or_equal:'.$minDate,
        ], [
            'date.after_or_equal' => 'The date cannot be more than 31 days ago.',
        ]);

        // Keep the current time of day when the expense is for today, otherwise record it at midday
        if ($request->date === $todayDate) {
            $expenseDate = Carbon::now(self::LOCAL_TIMEZONE);
        } else {
            $expenseDate = Carbon::parse($request->date.' 12:00', self::LOCAL_TIMEZONE);
        }

        $expense = new Expense;
        $expense->amount = $request->amount;
        $expense->expense_category_id = $request->expense_category_id;
        $expense->expense_description = $request->expense_description;
        $expense->created_at = $expenseDate->setTimezone('UTC');
        $expense->updated_at = Carbon::now();
        $expense->updated_by = Auth::id();
        $expense->save();

        $expense->load('category');

        session()->flash('last_expense', [
            'category' => $expense->category->name,
            'description' => $expense->expense_description,
            'amount' => (float) $expense->amount,
            // Stored as a string, not a Carbon instance: session data round-trips through JSON, which would silently turn it back into a string anyway
            'date' => CarbonImmutable::parse($expense->created_at)->setTimezone(self::LOCAL_TIMEZONE)->toISOString(),
        ]);

        return back();
    }

    public function update(Request $request)
    {
        $expense = Expense::findOrFail($request->id);
        $this->authorizeExpense($expense);

        $request->validate([
            'expense_date' => 'required|date',
            'expense_amount' => 'required',
            'expense_description' => 'max:200',
        ]);

        // updated_by records who entered the expense (it decides who can see it), so editing keeps it unchanged
        $expense->amount = $request->expense_amount;
        $expense->expense_category_id = $request->expense_category;
        $expense->expense_description = $request->expense_description;
        $expense->updated_at = Carbon::now();
        $expense->created_at = date('Y-m-d', strtotime($request->expense_date));
        $expense->save();

        session()->flash('alert-success', 'Expense updated successfully!');

        return back();
    }

    public function search(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Stop a user without "View Advanced Dashboard" from working on an expense someone else recorded.
     */
    private function authorizeExpense(Expense $expense): void
    {
        abort_unless($expense->isVisibleTo(Auth::user()), 403, 'You can only change expenses you recorded.');
    }
}
