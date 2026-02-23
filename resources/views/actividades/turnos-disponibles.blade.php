@extends('layouts.app')

@section('content')
    @livewire('actividades.turnos-disponibles', ['actividades' => $actividades])
@endsection
