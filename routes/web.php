<?php

use Illuminate\Support\Facades\Route;

// Route web par défaut — l'application utilise uniquement l'API REST
Route::get('/', function () {
    return response()->json(['message' => 'Gestion Stock API — voir /api/v1/']);
});
