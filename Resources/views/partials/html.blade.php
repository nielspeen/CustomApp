{{-- A callback's own HTML (callbacks without sidebar data, e.g. for Help Scout), under the app's title. --}}
<section class="conv-sidebar-block inspector-section">
    <h3>{{ $title }}</h3>
    {!! $html !!}
</section>
