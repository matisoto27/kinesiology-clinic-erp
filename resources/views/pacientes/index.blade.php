@php use Carbon\Carbon; @endphp

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto Kinésico</title>
    <link rel="icon" type="image/x-icon" href="../img/icono.ico">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-[#F8F4F4]">

    <header class="bg-[#006E6B] font-medium h-20"></header>

    <main>

        <div class="w-full max-w-screen-2xl mx-auto my-5 bg-[#006E6B] rounded-3xl py-6 px-8">
            
            <h2 class="mb-6 block font-semibold text-2xl text-center text-white">Listado de pacientes</h2>

            <table class="w-full rounded-lg overflow-hidden shadow-lg table-auto">

                <thead class="bg-[#014745] text-white">
                    <tr>
                        <th class="py-3 w-1/7">DNI</th>
                        <th class="py-3 w-1/7">Apellido</th>
                        <th class="py-3 w-1/7">Nombre</th>
                        <th class="py-3 w-1/7">Fecha de nacimiento</th>
                        <th class="py-3 w-1/7">Edad</th>
                        <th class="py-3 w-1/7">Telefono</th>
                        <th class="py-3 w-1/7">Fecha de inicio</th>
                    </tr>
                </thead>

                <tbody class="bg-white text-center">
                    @foreach($pacientes as $paciente)
                        <tr class="hover:bg-[#F5D500] transition-colors duration-100">
                            <td class="py-3 w-1/7">{{ $paciente->dni }}</td>
                            <td class="py-3 w-1/7">{{ $paciente->apellido }}</td>
                            <td class="py-3 w-1/7">{{ $paciente->nombre }}</td>
                            <td class="py-3 w-1/7">{{ Carbon::parse($paciente->fecha_nac)->format('d/m/Y') }}</td>
                            <td class="py-3 w-1/7">{{ Carbon::parse($paciente->fecha_nac)->age }}</td>
                            <td class="py-3 w-1/7">{{ $paciente->telefono }}</td>
                            <td class="py-3 w-1/7">{{ Carbon::parse($paciente->fecha_ingreso)->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                
            </table>

        </div>

    </main>

</body>

</html>