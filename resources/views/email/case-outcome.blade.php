<x-mail::message>
# Incident Case {{ ucfirst($reason) }}

Hello Executive Management,

This notification confirms that Case **#{{ strtoupper(substr($case->id, 0, 8)) }}** has been officially **{{ $reason }}** by {{ $case->assignedTo ? $case->assignedTo->first_name . ' ' . $case->assignedTo->last_name : 'the assigned investigator' }}.

<x-mail::panel>
### Case Summary
- **Case Reference:** `#{{ strtoupper(substr($case->id, 0, 8)) }}`
- **Classification:** {{ $case->category?->value ?? $case->category ?? 'General Incident' }}
- **Directorate:** {{ $case->department?->name ?? 'General Department' }}
- **Current Status:** {{ $case->status?->value ?? $case->status ?? strtoupper($reason) }}
@if($case->resolution_summary)
- **Resolution Summary:** {{ $case->resolution_summary }}
@endif
</x-mail::panel>

You may review the complete case history, audit logs, and evidentiary record in the Executive Governance Console.

<x-mail::button :url="config('app.frontend_url') . '/app/cases/' . $case->id">
View Case in Portal
</x-mail::button>

<x-slot:subcopy>
If you are having trouble clicking the button, copy and paste the following URL into your web browser:  
[{{ config('app.frontend_url') . '/app/cases/' . $case->id }}]({{ config('app.frontend_url') . '/app/cases/' . $case->id }})
</x-slot:subcopy>
</x-mail::message>