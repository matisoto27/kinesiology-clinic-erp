@extends('layouts.app')

@section('content')
    @livewire('pacientes-fijos.inicio', ['pacientesFijos' => $pacientesFijos])
@endsection
