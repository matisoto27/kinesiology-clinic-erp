@extends('layouts.app')

@section('content')
    @livewire('profesionales.editar', ['profesional' => $profesional])
@endsection
