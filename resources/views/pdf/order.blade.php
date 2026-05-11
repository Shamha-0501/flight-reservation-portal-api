<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 8px; }
    </style>
</head>
<body>
    <h1>Invoice</h1>

    <p>Name: {{ $data['name'] }}</p>
    <p>Total: {{ $data['total'] }}</p>
</body>
</html>