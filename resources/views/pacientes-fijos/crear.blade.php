@extends('layouts.app')

@section('content')
    @livewire('pacientes-fijos.crear', ['inscripciones' => $inscripciones])
@endsection
