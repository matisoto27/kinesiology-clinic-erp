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

<body class="min-h-screen flex flex-col">
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
                            <li><a href="{{ route('actividades.turnos-disponibles') }}">Consultar disponibilidad</a></li>
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
                                <li><a href="{{ route('actividades-combos.inicio') }}">Administrar combos de actividades</a></li>
                                <li><a href="{{ route('precios.crear') }}">Actualizar precios de los combos</a></li>
                                <li><a href="{{ route('obras-sociales.inicio') }}">Administrar obras sociales</a></li>
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

    <main class="flex-grow">
        @yield('content')
    </main>

    <footer class="py-6 bg-[#006E6B] border-white/10 border-t">
        <div class="mx-auto px-4 max-w-7xl flex flex-col md:flex-row justify-center items-center gap-4">
            <div class="flex items-center gap-3 group">
                <span class="text-white/50 text-xs font-light tracking-[0.4em] uppercase">
                    Desarrollado por
                </span>

                <div class="px-4 py-2 flex items-center gap-2 bg-black/10 hover:bg-black/20 border-white/5 border backdrop-blur-sm rounded-full transition-all duration-300">
                    <x-tempesta-logo />
                    <div class="flex flex-col leading-none">
                        <span class="text-white text-lg font-bold tracking-tighter">TEMPESTA</span>
                        <span class="ml-0.5 text-[#00ffa2] text-[10px] font-black tracking-[0.2em]">TECH</span>
                    </div>
                </div>
            </div>

            <div class="mx-2 h-8 w-[1px] hidden md:block bg-white/10"></div>

            <p class="text-white/30 text-xs font-light tracking-wide">
                &copy; {{ date('Y') }} — Todos los derechos reservados.
            </p>
        </div>
    </footer>

    @stack('scripts')
</body>

</html>
