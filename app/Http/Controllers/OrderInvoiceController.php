<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderInvoiceController extends Controller
{
    /**
     * Display a simple invoice view for the given order.
     */
    public function show(Order $order)
    {
        // For now return a simple blade view. The view can be extended later
        // to render a printable invoice or generate a PDF.
        return view('orders.invoice', compact('order'));
    }
}
