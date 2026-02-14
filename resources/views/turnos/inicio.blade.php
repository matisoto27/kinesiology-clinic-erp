@extends('layouts.app')

@section('content')
    @livewire('turnos.inicio', ['actividades' => $actividades])
@endsection
