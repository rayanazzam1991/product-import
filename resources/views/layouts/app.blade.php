<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <!-- Tailwind CSS CDN for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Inter font family -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f9;
        }
    </style>
</head>
<body>
<header class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-gray-800">Warehouse & Product Management</h2>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="mt-12 py-4 text-center text-gray-500 text-sm border-t border-gray-200">
    &copy; {{ date('Y') }} Inventory Dashboard. All rights reserved.
</footer>

<!-- Push scripts here (e.g., the JavaScript toggles from index.blade.php) -->
@stack('scripts')
</body>
</html>
