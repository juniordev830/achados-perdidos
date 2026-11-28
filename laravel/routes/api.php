<?php
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/relatorios/consulta', [ReportController::class, 'search']);
Route::post('/relatorios/resumo', [ReportController::class, 'summary']);
