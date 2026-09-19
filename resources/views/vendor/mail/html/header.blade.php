@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Verita')
                <img src="{{ asset('images/verita-logo.png') }}" class="logo" alt="App Logo">
            @else
                {!! $slot !!}
            @endif
        </a>
    </td>
</tr>
