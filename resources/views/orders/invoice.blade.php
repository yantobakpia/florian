<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Order #{{ $order->id }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; padding: 20px; }
        .header { display:flex; justify-content:space-between; align-items:center; }
        .section { margin-top: 20px; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:8px; border:1px solid #ddd; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice</h1>
        <div>
            <strong>Order #{{ $order->id }}</strong><br>
            {{ $order->created_at?->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="section">
        <h3>Customer</h3>
        <div>
            {{ $order->customer?->name ?? '—' }}<br>
            {{ $order->customer?->phone ?? '' }}
        </div>
    </div>

    <div class="section">
        <h3>Order summary</h3>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $order->description ?? 'Order items' }}</td>
                    <td>1</td>
                    <td>{{ number_format($order->total ?? 0, 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right"><strong>Total</strong></td>
                    <td><strong>{{ number_format($order->total ?? 0, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

</body>
</html>
