@extends('layouts.app')

@push('styles')
    <style>
        [x-cloak] { display: none !important; }
    </style>
@endpush

@section('content')
    @livewire('pacientes.inicio')
@endsection
