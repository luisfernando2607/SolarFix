@extends('layouts.app')

@section('title', 'Notificaciones')

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Notificaciones</h2>
        @if (Auth::user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="text-sm text-blue-600 hover:underline">Marcar todas como leídas</button>
            </form>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border divide-y">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-gray-50 {{ $notification->read_at ? '' : 'bg-blue-50/60' }}">
                <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $notification->read_at ? 'bg-transparent' : 'bg-blue-600' }}"></span>
                <span class="flex-1 min-w-0">
                    <span class="block text-sm {{ $notification->read_at ? 'text-gray-700' : 'font-semibold text-gray-900' }}">{{ $notification->data['title'] ?? 'Aviso' }}</span>
                    <span class="block text-sm text-gray-600 truncate">{{ $notification->data['body'] ?? '' }}</span>
                    <span class="block text-xs text-gray-400 mt-0.5">{{ $notification->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-gray-500">No tienes notificaciones.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
@endsection
