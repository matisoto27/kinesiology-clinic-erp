@extends('layouts.app')

@section('content')
    @livewire('turnos.calendario', ['tiposActividad' => $tiposActividad])
@endsection
