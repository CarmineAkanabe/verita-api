<x-mail::message>
# Case Intake Ready for Review

Hello Officer,

A new whistleblower disclosure in **{{ $case->department?->name ?? 'your department' }}** has completed automated AI intake processing and is now awaiting assessment in your departmental triage queue.

<x-mail::panel>
### Incident Particulars
- **Case Reference:** `#{{ strtoupper(substr($case->id, 0, 8)) }}`
- **Classification:** {{ $case->category?->value ?? $case->category ?? 'General Incident' }}
- **Directorate:** {{ $case->department?->name ?? 'General Department' }}
- **Financial Exposure:** {{ number_format((float) ($case->amount_involved ?? 0), 0, ',', ' ') }} FCFA
- **Date of Incident:** {{ $case->transaction_date ? \Carbon\Carbon::parse($case->transaction_date)->format('d M Y') : 'Not specified' }}
- **Person / Unit Cited:** {{ $case->person_involved ?: 'Unspecified' }}
</x-mail::panel>

### Next Steps
Review the synthesized incident chronology and evidence vault. You may claim this docket in the staff portal to begin active investigation.

<x-mail::button :url="config('app.frontend_url') . '/app/cases/' . $case->id">
Review Case in Queue
</x-mail::button>

<x-slot:subcopy>
If you are having trouble clicking the button, copy and paste the following URL into your web browser:  
[{{ config('app.frontend_url') . '/app/cases/' . $case->id }}]({{ config('app.frontend_url') . '/app/cases/' . $case->id }})
</x-slot:subcopy>
</x-mail::message>