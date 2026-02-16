@extends('layouts.app')

@section('content')
    @livewire('profesionales.horas-trabajadas.crear', ['profesionales' => $profesionales])
@endsection
