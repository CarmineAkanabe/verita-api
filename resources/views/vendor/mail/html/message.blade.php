<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.frontend_url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
<div style="text-align: center; color: #6B7280; font-size: 11px; line-height: 1.6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
<p style="margin: 0 0 4px 0; font-weight: 600; color: #4B5563;">
Verita Enterprise Trust Platform · Digimark Enterprise Compliance Desk
</p>
<p style="margin: 0 0 6px 0; color: #9CA3AF; font-size: 10px;">
Douala &amp; Yaoundé Regional Operational Nodes · CEMAC Corporate Compliance
</p>
<p style="margin: 0; color: #9CA3AF; font-size: 10px; line-height: 1.4;">
<strong>CONFIDENTIALITY NOTICE:</strong> This notification contains privileged whistleblower communications managed under ISO 37002 anti-retaliation and zero-knowledge privacy protocols. No IP addresses or device identifiers are retained.
</p>
<p style="margin: 6px 0 0 0; color: #9CA3AF; font-size: 10px;">
&copy; {{ date('Y') }} Verita Technologies Inc. All rights reserved.
</p>
</div>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>