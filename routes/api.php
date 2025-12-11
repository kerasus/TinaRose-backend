<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ColorController;
use App\Http\Controllers\Api\FabricController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\ProductPartController;
use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\InventoryCountController;
use App\Http\Controllers\Api\DatabaseBackupController;
use App\Http\Controllers\Api\TransferPackageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::get('test', [AuthController::class, 'test']);
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Protected routes (require authentication via Sanctum)
Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('users', '\\'.UserController::class);
    Route::apiResource('colors', '\\'.ColorController::class);
    Route::apiResource('fabrics', '\\'.FabricController::class);
    Route::apiResource('products', '\\'.ProductController::class);
    Route::apiResource('transfers', '\\'.TransferController::class);
    Route::apiResource('inventories', '\\'.InventoryController::class);
    Route::apiResource('productions', '\\'.ProductionController::class);
    Route::apiResource('product-parts', '\\'.ProductPartController::class);
    Route::apiResource('raw-materials', '\\'.RawMaterialController::class);
    Route::apiResource('transfer-packages', '\\'.TransferPackageController::class);
    Route::apiResource('inventory-counts', InventoryCountController::class)->only(['index', 'show', 'destroy']);

    Route::post('inventory-counts/start', [InventoryCountController::class, 'start']);
    Route::get('inventory-counts/{id}/items', [InventoryCountController::class, 'getInventoryItemsPaginated']);
    Route::put('inventory-counts/{id}/item', [InventoryCountController::class, 'updateItem']);
    Route::post('inventory-counts/{id}/finalize', [InventoryCountController::class, 'finalize']);

    Route::post('transfers/{transfer}/items', [TransferController::class, 'addItem']);
    Route::post('transfers/{transfer}/approve', [TransferController::class, 'approve']);
    Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject']);
    Route::delete('transfers/{transfer}/items/{item}', [TransferController::class, 'removeItem']);

    Route::post('products/{product}/requirements', [ProductController::class, 'addRequirement']);
    Route::put('products/{product}/requirements/{requirement}', [ProductController::class, 'updateRequirement']);
    Route::delete('products/{product}/requirements/{requirement}', [ProductController::class, 'removeRequirement']);

    Route::post('users/{userId}/assign-role', [UserController::class, 'assignRole']);
    Route::post('users/{userId}/remove-role', [UserController::class, 'removeRole']);

    Route::delete('inventories/{inventoryId}/items/{inventoryItemId}/destroy', [InventoryController::class, 'destroyInventoryItem']);
    Route::post('inventories/initialize', [InventoryController::class, 'initializeInventories']);
    Route::get('inventories/{inventory}/items', [InventoryController::class, 'inventoryItems']);

    Route::get('reports/pending-transfers-count/my/count', [ReportController::class, 'getPendingTransfersCountForUser']);
    Route::get('reports/pending-transfers-count/all/count', [ReportController::class, 'getAllPendingTransfersCount']);
    Route::get('reports/locked-inventories/count', [ReportController::class, 'getLockedInventoriesCount']);

    Route::post('product-parts/{product_part}/requirements', [ProductPartController::class, 'addRequirement']);
    Route::put('product-parts/{product_part}/requirements/{requirement}', [ProductPartController::class, 'updateRequirement']);
    Route::delete('product-parts/{product_part}/requirements/{requirement}', [ProductPartController::class, 'removeRequirement']);

    Route::post('users/{user_id}/reset-password', [AuthController::class, 'resetPassword'])->name('users.reset.password');

    Route::get('productions/summary', [ProductionController::class, 'summary']);
    Route::get('productions/user-summary', [ProductionController::class, 'userSummary']);
    Route::get('productions/summary-export', [ProductionController::class, 'summaryExport']);
    Route::get('productions/user-summary-export', [ProductionController::class, 'userSummaryExport']);
    Route::post('productions/{production}/approve', [ProductionController::class, 'approve']);

    Route::post('database/backup', [DatabaseBackupController::class, 'backupDatabase']);
});
