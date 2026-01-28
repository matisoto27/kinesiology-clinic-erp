@php
    $columnas = [
        'dni' => 'DNI',
        'nombre' => 'Nombre',
        'apellido' => 'Apellido',
        'fecha_nac' => 'Fecha de nacimiento',
        'edad' => 'Edad',
        'domicilio' => 'Domicilio',
        'telefono' => 'Teléfono',
        'profesion' => 'Profesión',
        'created_at' => 'Fecha de ingreso'
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-listado entidad="paciente" titulo="Pacientes" :elementos="$pacientes" :columnas="$columnas" rutaEditar="pacientes.editar" rutaEliminar="pacientes.eliminar" />
@endsection
