<?php

namespace Modules\Medical\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MedicalQuoteController extends Controller
{
    public function calculate(Request $request)
    {
        return response()->json([
            'module' => 'Medical',
            'status' => 'active',
            'quote' => 1500.00
        ]);
    }
}