@extends('layouts.app')

@section('content')
    @livewire('principal', ['tiposActividad' => $tiposActividad])
@endsection
