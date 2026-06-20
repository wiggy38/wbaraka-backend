<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgentAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OffreController;
use App\Http\Controllers\PortailController;
use App\Http\Controllers\SimulationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('health', function () {
    DB::connection()->getPdo();
    return response()->json(['status' => 'ok']);
});

Route::prefix('v1')->group(function () {

    // --- Auth publique (OTP client) ---
    Route::prefix('auth')->group(function () {
        Route::post('otp/request', [AuthController::class, 'requestOtp']);
        Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
    });

    // --- Offres publiques (lecture seule) ---
    Route::get('offres', [OffreController::class, 'index']);
    Route::get('offres/{id}/simulation-params', [OffreController::class, 'simulationParams']);
    Route::get('offres/{id}', [OffreController::class, 'show']);

    // --- Simulations ---
    Route::post('simulations/preview', [SimulationController::class, 'preview']);
    Route::post('simulations', [SimulationController::class, 'store'])->middleware('auth.user.optional');
    Route::get('simulations/{id}', [SimulationController::class, 'show']);

    Route::middleware('auth.user')->group(function () {
        Route::get('users/me/simulations', [SimulationController::class, 'mySimulations']);
        Route::put('auth/me/nom', [AuthController::class, 'updateNom']);
    });

    // --- Portail agent ---
    Route::prefix('portail')->group(function () {
        Route::post('auth/login', [AgentAuthController::class, 'login']);

        Route::middleware('auth.portail')->group(function () {
            Route::get('dashboard', [PortailController::class, 'dashboard']);
            Route::get('profil', [PortailController::class, 'profil']);
            Route::put('profil', [PortailController::class, 'updateProfil']);
            Route::get('stats', [PortailController::class, 'stats']);
            Route::get('stats/offre/{id}', [PortailController::class, 'statsOffre']);
            Route::get('stats/export', [PortailController::class, 'statsExport']);
            Route::get('offres', [OffreController::class, 'index']);
            Route::post('offres', [OffreController::class, 'store']);
            Route::put('offres/{id}', [OffreController::class, 'update']);
            Route::delete('offres/{id}', [OffreController::class, 'destroy']);
        });
    });

    // --- Admin ---
    Route::prefix('admin')->group(function () {
        Route::post('auth/login', [AdminAuthController::class, 'login']);

        // Public — no token required
        Route::get('slider', [AdminController::class, 'indexSlider']);

        Route::middleware('auth.admin')->group(function () {
            Route::get('dashboard', [AdminController::class, 'dashboard']);

            Route::prefix('moderation')->group(function () {
                Route::get('offres', [AdminController::class, 'indexOffresModeration']);
                Route::post('offres/{id}/approuver', [AdminController::class, 'approuverOffre']);
                Route::post('offres/{id}/rejeter', [AdminController::class, 'rejeterOffre']);
            });

            Route::prefix('imfs')->group(function () {
                Route::get('/', [AdminController::class, 'indexImfs']);
                Route::post('/', [AdminController::class, 'createImf']);
                Route::put('{id}/suspendre', [AdminController::class, 'suspendrImf']);
                Route::put('{id}/reactiver', [AdminController::class, 'reactiverImf']);
                Route::delete('{id}', [AdminController::class, 'supprimerImf']);
            });

            Route::get('journal', [AdminController::class, 'indexJournal']);

            Route::put('slider/{id}', [AdminController::class, 'updateSlider']);
        });
    });
});
