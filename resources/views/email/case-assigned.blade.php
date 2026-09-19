{{-- case-assigned.blade.php --}}
<x-mail::message>
    # Case assigned to you

    Case **#{{ $case->id }}** has been assigned to you.

    <x-mail::button :url="config('app.frontend_url') . '/cases/' . $case->id">
        Open Case
    </x-mail::button>

    Thanks,<br>{{ config('app.name') }}
</x-mail::message>
