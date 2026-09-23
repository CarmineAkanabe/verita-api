<x-mail::message>
    # New Case Reporter Consultation Message

    Hello Officer,

    A new confidential message has been received from the anonymous Case Reporter on Case
    **#{{ strtoupper(substr($message->case_record_id, 0, 8)) }}**.

    <x-mail::panel>
        ### Communication Alert
        - **Case Reference:** `#{{ strtoupper(substr($message->case_record_id, 0, 8)) }}`
        - **Sender:** Case Reporter (Anonymous Relay)
        - **Timestamp:** {{ \Carbon\Carbon::parse($message->sent_at ?? now())->format('d M Y, H:i') }}
    </x-mail::panel>

    Because this matter is under active investigation, please access the consultation channel to read the disclosure and
    provide a timely response.

    <x-mail::button :url="config('app.frontend_url') . '/app/cases/' . $message->case_record_id . '/chat'">
        Open Consultation Channel
    </x-mail::button>

    <x-slot:subcopy>
        If you are having trouble clicking the button, copy and paste the following URL into your web browser:
        [{{ config('app.frontend_url') . '/app/cases/' . $message->case_record_id . '/chat' }}]({{ config('app.frontend_url') . '/app/cases/' . $message->case_record_id . '/chat' }})
    </x-slot:subcopy>
</x-mail::message>
