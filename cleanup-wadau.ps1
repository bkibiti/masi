# ---------------------------------------------------------------------------
# Wadau cleanup: removes the ABG modules Wadau does not use
# (sales, products/inventory, purchases, partnership, customers).
#
# Run from the project folder in Laragon's terminal (PowerShell):
#   cd C:\laragon\www\wadau
#   powershell -ExecutionPolicy Bypass -File cleanup-wadau.ps1
#
# Everything is in git, so a deleted file can be brought back with
#   git checkout HEAD -- <path>
# ---------------------------------------------------------------------------

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$paths = @(
    # Models
    'app\Models\Customer.php',
    'app\Models\Partner.php',
    'app\Models\PartnerContribution.php',
    'app\Models\PartnerPayout.php',
    'app\Models\PaymentMethod.php',
    'app\Models\ProductCategory.php',
    'app\Models\Purchase.php',
    'app\Models\Sale.php',
    'app\Models\Stock.php',
    'app\Models\StockAdjustment.php',
    'app\Models\Supplier.php',

    # Controllers
    'app\Http\Controllers\CurrentStockController.php',
    'app\Http\Controllers\CustomerController.php',
    'app\Http\Controllers\PartnerContributionController.php',
    'app\Http\Controllers\PartnerController.php',
    'app\Http\Controllers\PartnerPayoutController.php',
    'app\Http\Controllers\ProductCategoryController.php',
    'app\Http\Controllers\ProductController.php',
    'app\Http\Controllers\PurchaseController.php',
    'app\Http\Controllers\SaleController.php',
    'app\Http\Controllers\StockAdjustmentController.php',
    'app\Http\Controllers\SupplierController.php',
    'app\Http\Requests\ProductStoreRequest.php',

    # Services (sales/partnership cash + dashboard)
    'app\Services',

    # Views
    'resources\views\customers',
    'resources\views\dashboards',
    'resources\views\inventory',
    'resources\views\partnership',
    'resources\views\purchases',
    'resources\views\sales',
    'resources\views\masters\adjustment_reason',
    'resources\views\masters\categories',
    'resources\views\masters\locations',
    'resources\views\masters\products',
    'resources\views\masters\suppliers',
    'resources\views\reports\total_daily_sales.blade.php',
    'resources\views\reports\total_daily_sales_pdf.blade.php',
    'resources\views\reports\total_monthly.blade.php',
    'resources\views\reports\total_monthly_pdf.blade.php',

    # Old settings edit modal (Settings is now a single form)
    'resources\views\configurations\edit.blade.php',

    # Old dashboard chart script
    'resources\js\dashboard.js',

    # ABG-only SQL scripts
    'database\sql\partner_payouts.sql',
    'database\sql\partnership_seed_2025_2026.sql',
    'database\sql\partnership.sql',
    'database\sql\pos_simple_update.sql'
)

foreach ($p in $paths) {
    if (Test-Path $p) {
        Remove-Item -Recurse -Force $p
        Write-Host "Deleted  $p" -ForegroundColor Yellow
    } else {
        Write-Host "Skipped  $p (not found)" -ForegroundColor DarkGray
    }
}

Write-Host ''
Write-Host 'Refreshing autoload and caches...' -ForegroundColor Cyan
composer dump-autoload
php artisan optimize:clear
php artisan permission:cache-reset

Write-Host ''
Write-Host 'Done. Next: run database\sql\wadau_setup.sql on the wadau database, then npm run build.' -ForegroundColor Green
