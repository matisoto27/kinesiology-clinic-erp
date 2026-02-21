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
    <header class="bg-[#006E6B] font-medium h-20 shadow-md">
        <div class="mx-auto h-full w-[90%] lg:w-[80%] flex items-center text-white">

            <a href="{{ route('inicio') }}" class="flex items-center gap-3 bg-white rounded-sm overflow-hidden hover:opacity-90 transition">
                <h1 class="text-black text-[28px] pl-2" style="font-family: 'Lato', sans-serif; font-weight: 500;">Punto</h1>
                <img src="{{ asset('img/logo.png') }}" alt="Logo Punto Kinésico" class="h-12 w-auto">
                <h1 class="text-black text-[28px] pr-2" style="font-family: 'Lato', sans-serif; font-weight: 500;">Kinésico</h1>
            </a>

            <nav class="ml-auto h-full">
                <ul class="h-full flex gap-2 text-sm lg:text-base">
                    <li>
                        <a href="{{ route('inicio') }}" class="px-4 h-full flex items-center hover:bg-[#2f7a79] transition">
                            Inicio
                        </a>
                    </li>

                    <li class="menu-desplegable group">
                        <button>
                            Pacientes
                            <x-iconos.flecha-abajo />
                        </button>
                        <ul>
                            <li><a href="{{ route('pacientes.inicio') }}">Lista de pacientes</a></li>
                            <li><a href="{{ route('pacientes.crear') }}">Registrar paciente</a></li>
                            <li><a href="{{ route('obras-sociales-pacientes.crear') }}">Actualizar obra social de un paciente</a></li>
                        </ul>
                    </li>

                    <li class="menu-desplegable group">
                        <button>
                            Inscripciones
                            <x-iconos.flecha-abajo />
                        </button>
                        <ul>
                            <li><a href="{{ route('actividades-pacientes.inicio') }}">Historial de inscripciones</a></li>
                            <li><a href="{{ route('actividades-pacientes.general.crear') }}">Nueva inscripción Gym/Pilates</a></li>
                            <li><a href="{{ route('actividades-pacientes.kinesiologia.con-orden.crear') }}">Kinesiología (CON orden médica)</a></li>
                            <li><a href="{{ route('actividades-pacientes.kinesiologia.sin-orden.crear') }}">Kinesiología (SIN orden médica)</a></li>
                            <li><a href="{{ route('actividades-pacientes.aplicar-orden') }}">Aplicar orden médica a una inscripción</a></li>
                            <li><a href="{{ route('precios.crear') }}">Actualizar precios de los combos</a></li>
                        </ul>
                    </li>

                    <li class="menu-desplegable group">
                        <button>
                            Pacientes Fijos
                            <x-iconos.flecha-abajo />
                        </button>
                        <ul>
                            <li><a href="{{ route('pacientes-fijos.inicio') }}">Lista de pacientes fijos</a></li>
                            <li><a href="{{ route('pacientes-fijos.crear') }}">Registrar paciente fijo</a></li>
                        </ul>
                    </li>

                    <li class="menu-desplegable group">
                        <button>
                            Turnos
                            <x-iconos.flecha-abajo />
                        </button>
                        <ul>
                            <li><a href="{{ route('turnos.inicio') }}">Historial de turnos</a></li>
                            <li><a href="{{ route('turnos.calendario') }}">Ver calendario</a></li>
                        </ul>
                    </li>

                    <li class="menu-desplegable group">
                        <button>
                            Gestión de caja
                            <x-iconos.flecha-abajo />
                        </button>
                        <ul>
                            <li><a href="{{ route('movimientos') }}">Movimientos de caja</a></li>
                            <li><a href="{{ route('pagos.crear') }}">Registrar pago</a></li>
                            <li><a href="{{ route('egresos.crear') }}">Registrar egreso</a></li>
                        </ul>
                    </li>

                    <li class="menu-desplegable group">
                        <button>
                            Para el personal
                            <x-iconos.flecha-abajo />
                        </button>
                        <ul>
                            <li><a href="{{ route('horas-trabajadas.crear') }}">Registrar horas trabajadas</a></li>
                        </ul>
                    </li>

                    @if (session('acceso_admin'))
                        <li class="menu-desplegable group">
                            <button>
                                Administración
                                <x-iconos.flecha-abajo />
                            </button>
                            <ul>
                                <li><a href="{{ route('profesionales.crear') }}">Registrar nuevo profesional</a></li>
                                <li><a href="{{ route('profesionales.inicio') }}">Lista de profesionales</a></li>
                                <li><a href="{{ route('horas-trabajadas.inicio') }}">Historial de horas trabajadas</a></li>
                                <li>
                                    <form action="{{ route('admin.salir') }}" method="POST" class="px-4 py-3 block hover:bg-red-700">
                                        @csrf
                                        <button type="submit">
                                            Cerrar sesión admin
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li>
                            <a href="{{ route('admin.inicio') }}" class="px-4 h-full flex items-center hover:bg-[#2f7a79] transition">
                                Ingresar como administrador
                            </a>
                        </li>
                    @endif
                </ul>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>

</html>
