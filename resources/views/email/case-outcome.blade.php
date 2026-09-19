{{-- case-outcome.blade.php --}}
<x-mail::message>
    # Case {{ $reason }}

    Case **#{{ $case->id }}** was {{ $reason }}.

    <x-mail::button :url="config('app.frontend_url') . '/cases/' . $case->id">
        Open Case
    </x-mail::button>

    Thanks,<br>{{ config('app.name') }}
</x-mail::message>
