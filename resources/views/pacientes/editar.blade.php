@extends('layouts.app')

@section('content')
    @livewire('pacientes.editar', ['paciente' => $paciente])
@endsection
