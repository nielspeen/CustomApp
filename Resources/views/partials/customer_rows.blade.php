{{-- A section's rows: the label above its value; only warnings and problems get a badge. --}}
<x-fruit::description-list class="customapp-rows">
    @foreach ($rows as $row)
        <div>
            <dt>@if ($row['url'])<a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['label'] }}</a>@else{{ $row['label'] }}@endif</dt>
            <dd>
                @if ($row['value'] !== '')
                    @if ($row['tone'])<x-fruit::badge :tone="$row['tone']">{{ $row['value'] }}</x-fruit::badge>@else{{ $row['value'] }}@endif
                @endif
                @if ($row['detail'] !== '')<p class="f-help">{{ $row['detail'] }}</p>@endif
                @if ($row['links'])
                    <p class="customapp-links">@foreach ($row['links'] as $link)<a href="{{ $link['url'] }}" target="_blank" rel="noopener" @if ($link['title'] !== '') title="{{ $link['title'] }}" @endif>{{ $link['text'] }}</a>@if (!$loop->last) · @endif @endforeach</p>
                @endif
            </dd>
        </div>
    @endforeach
</x-fruit::description-list>
