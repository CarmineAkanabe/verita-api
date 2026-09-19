{{-- case-ready-for-review.blade.php --}}
<x-mail::message>
    # Case ready for review

    Case **#{{ $case->id }}** in {{ $case->department->name }} has finished AI processing and is ready for review.

    <x-mail::button :url="config('app.frontend_url') . '/cases/' . $case->id">
        Open Case
    </x-mail::button>

    Thanks,<br>{{ config('app.name') }}
</x-mail::message>
