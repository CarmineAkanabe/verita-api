{{-- new-message.blade.php --}}
<x-mail::message>
    # New message

    You have a new message on case **#{{ $message->case_record_id }}**.

    <x-mail::button :url="config('app.frontend_url') . '/cases/' . $message->case_record_id . '/chat'">
        Open Chat
    </x-mail::button>

    Thanks,<br>{{ config('app.name') }}
</x-mail::message>
