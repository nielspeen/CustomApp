{{-- The callback's customer (sidebar data, version 1; CustomAppController::sidebarData()), as inspector sections. --}}
<section class="conv-sidebar-block inspector-section customapp-customer">
    <h3>{{ $title }}</h3>
    @if ($sidebar['title'] !== '')
        <p class="customapp-customer__name">@if ($sidebar['url'])<a href="{{ $sidebar['url'] }}" target="_blank" rel="noopener">{{ $sidebar['title'] }}</a>@else{{ $sidebar['title'] }}@endif</p>
    @endif
</section>
@foreach ($sidebar['sections'] as $section)
    <section class="conv-sidebar-block inspector-section customapp-group">
        @if ($section['collapsed'])
            <x-fruit::disclosure :title="trim($section['title'].' · '.count($section['rows']), ' ·')">
                @include('customapp::partials/customer_rows', ['rows' => $section['rows']])
            </x-fruit::disclosure>
        @else
            @if ($section['title'] !== '')<h3>{{ $section['title'] }}</h3>@endif
            @include('customapp::partials/customer_rows', ['rows' => $section['rows']])
        @endif
    </section>
@endforeach
