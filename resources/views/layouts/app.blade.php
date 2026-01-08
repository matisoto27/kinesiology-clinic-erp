<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Punto Kinésico</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/icono.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <header class="bg-[#006E6B] font-medium h-20">
        <div class="flex mx-auto text-white h-full items-center w-[80%]">

            <div class="flex bg-[white] items-center gap-3 rounded-sm">
                <h1 class="text-black text-[28px] pl-2" style="font-family: 'Lato', sans-serif; font-weight: 500;">Punto</h1>
                <img src="{{ asset('img/logo.png') }}" alt="Logo Punto Kinésico" class="h-12 w-auto">
                <h1 class="text-black text-[28px] pr-2" style="font-family: 'Lato', sans-serif; font-weight: 500;">Kinésico</h1>
            </div>

            <ul class="ml-auto flex h-full gap-10 text-base">
                <li class="h-full">
                    <a href="../templates/index.html" class="h-full flex items-center px-6 hover:bg-[#2f7a79] transition">Asistencia de hoy</a>
                </li>
                <li class="h-full">
                    <a href="../templates/cobrosPacientes.html" class="h-full flex items-center px-6 hover:bg-[#2f7a79] transition">Cobros pacientes</a>
                </li>
                <li class="h-full">
                    <a href="../templates/registroDeActividades.html" class="h-full flex items-center px-6 hover:bg-[#2f7a79] transition">Registro de actividades</a>
                </li>
                <li class="h-full">
                    <a href="../templates/turnos.html" class="h-full flex items-center px-6 hover:bg-[#2f7a79] transition">Turnos</a>
                </li>
            </ul>

        </div>
    </header>

    <main>
        @yield('content')
    </main>

    @stack('scripts')

</body>

</html>