<x-mail::message>
# Incident Case Assigned to You

Hello {{ $case->assignedTo ? $case->assignedTo->first_name : 'Officer' }},

A confidential incident disclosure has been assigned to your investigation docket by Executive Management. Under Verita corporate governance and ISO 37002 compliance protocols, you have been designated as the primary investigator for this case.

<x-mail::panel>
### Incident Particulars
- **Case Reference:** `#{{ strtoupper(substr($case->id, 0, 8)) }}`
- **Classification:** {{ $case->category?->value ?? $case->category ?? 'General Incident' }}
- **Affected Directorate:** {{ $case->department?->name ?? 'General Department' }}
- **Financial Exposure:** {{ number_format((float) ($case->amount_involved ?? 0), 0, ',', ' ') }} FCFA
- **Date of Incident:** {{ $case->transaction_date ? \Carbon\Carbon::parse($case->transaction_date)->format('d M Y') : 'Not specified' }}
- **Person / Unit Cited:** {{ $case->person_involved ?: 'Unspecified' }}
@if($case->concerns_department_head)
- **Governance Flag:** ⚠️ Marked for supervisory conflict of interest screening
@endif
</x-mail::panel>

@if($case->purpose_of_transaction)
**Reported Purpose / Summary:**  
_{{ $case->purpose_of_transaction }}_
@endif

### Required Action
Please log in to the secure staff portal to examine attached evidentiary files, review automated AI timeline findings, and initiate confidential two-way communication with the whistleblower.

<x-mail::button :url="config('app.frontend_url') . '/app/cases/' . $case->id">
Open Case in Portal
</x-mail::button>

<x-slot:subcopy>
If you are having trouble clicking the "Open Case in Portal" button, copy and paste the following URL into your web browser:  
[{{ config('app.frontend_url') . '/app/cases/' . $case->id }}]({{ config('app.frontend_url') . '/app/cases/' . $case->id }})
</x-slot:subcopy>
</x-mail::message>