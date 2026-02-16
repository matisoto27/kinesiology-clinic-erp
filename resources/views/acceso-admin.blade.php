@extends('layouts.app')

@section('content')
    <div class="contenedor max-w-lg">
        <form action="{{ route('admin.verificar') }}" method="POST" class="formulario">
            @csrf
            <h2 class="titulo-formulario">Acceder al sistema como administrador</h2>
            <div class="mb-4 flex flex-col gap-1">
                <label for="input-codigo" class="etiqueta-formulario">Código de acceso</label>
                <input
                    id="input-codigo"
                    name="codigo"
                    type="password"
                    placeholder="Ingrese código de administrador"
                    class="entrada focus:outline-none focus:ring-2 focus:ring-[#F5D500] focus:border-[#F5D500]"
                    autofocus
                    required
                />
                @error('error') 
                    <div class="text-red-500 text-md">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="boton-registrar">Verificar</button>
        </form>
    </div>
@endsection
