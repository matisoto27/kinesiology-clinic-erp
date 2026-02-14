@props(['tipo'])

@if (session()->has($tipo))
    <div class="mb-4 p-4 rounded-lg shadow-md {{ $tipo === 'exito' ? 'bg-green-100 border-green-500 border-l-4 text-green-700 animate-fade-in' : 'bg-red-600 text-red-100 animate-bounce' }}">
        <div class="flex items-start">
            @if ($tipo === 'exito')
                <svg class="w-6 h-6 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
            @elseif ($tipo === 'error')
                <svg class="w-6 h-6 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
            @endif
            <div class="flex-1 min-w-0">
                <span class="block break-words font-bold">{{ session($tipo) }}</span>
            </div>
        </div>
    </div>
@endif
