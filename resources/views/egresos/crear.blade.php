@extends('layouts.app')

@section('content')
    @livewire('egresos.crear', ['profesionales' => $profesionales])
@endsection
