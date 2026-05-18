<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAG Package Testing</title>
    
    <!-- Livewire v3 handles its own styles, but we need Tailwind/Vite for your UI layout -->
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

    <!-- Your package views will be injected right here -->
    {{ $slot }}

    @livewireScripts
</body>
</html>