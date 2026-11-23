<?php

use Illuminate\Support\Facades\Route;

// The UI lives in the React app (../frontend). This root just confirms the API is up.
Route::get('/', fn () => response()->json(['app' => config('app.name'), 'api' => url('/api')]));
