<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ColorController;
use App\Http\Controllers\Api\FabricController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\ProductPartController;
use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\DatabaseBackupController;

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
Route::get('/test', [AuthController::class, 'test']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (require authentication via Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/users/{user_id}/reset-password', [AuthController::class, 'resetPassword'])->name('users.reset.password');

    Route::get('productions/summary', [ProductionController::class, 'summary']);
    Route::get('productions/summary-export', [ProductionController::class, 'summaryExport']);

    Route::apiResource('users', '\\'.UserController::class);
    Route::apiResource('colors', '\\'.ColorController::class);
    Route::apiResource('fabrics', '\\'.FabricController::class);
    Route::apiResource('products', '\\'.ProductController::class);
    Route::apiResource('transfers', '\\'.TransferController::class);
    Route::apiResource('inventories', '\\'.InventoryController::class);
    Route::apiResource('productions', '\\'.ProductionController::class);
    Route::apiResource('product-parts', '\\'.ProductPartController::class);
    Route::apiResource('raw-materials', '\\'.RawMaterialController::class);

    Route::post('/transfers/{transfer}/items', [TransferController::class, 'addItem']);
    Route::delete('/transfers/{transfer}/items/{item}', [TransferController::class, 'removeItem']);

    Route::post('/products/{product}/requirements', [ProductController::class, 'addRequirement']);
    Route::put('/products/{product}/requirements/{requirement}', [ProductController::class, 'updateRequirement']);
    Route::delete('/products/{product}/requirements/{requirement}', [ProductController::class, 'removeRequirement']);

    Route::post('/users/{userId}/assign-role', [UserController::class, 'assignRole']);
    Route::post('/users/{userId}/remove-role', [UserController::class, 'removeRole']);

    Route::get('/inventories/{inventory}/items', [InventoryController::class, 'inventoryItems']);

    Route::post('/database/backup', [DatabaseBackupController::class, 'backupDatabase']);
});
