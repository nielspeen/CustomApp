<form class="settings-form" method="POST" action="">
    {{ csrf_field() }}

    <x-fruit::form-section title="Custom App">
        <x-fruit::field :label="__('Title')" :description="__('The title of the custom app. This is used to display the title of the custom app in the sidebar.')" layout="row">
            <x-fruit::input name="settings[customapp.title]" :value="$settings['customapp.title']" />
        </x-fruit::field>

        <x-fruit::field :label="__('Callback URL')" :description="__('Example').': https://crm.example.org/api/freescout/callback'" layout="row">
            <x-fruit::input name="settings[customapp.callback_url]" :value="old('settings') ? old('settings')['customapp.callback_url'] : $settings['customapp.callback_url']" />
        </x-fruit::field>

        <x-fruit::field :label="__('Secret Key')" :description="__('The secret key used to generate a signature header. This can be used to verify the authenticity of the request.')" layout="row">
            <x-fruit::input name="settings[customapp.secret_key]" :value="$settings['customapp.secret_key']" />
        </x-fruit::field>

        <x-fruit::field label="Signature Header" :description="__('Select the signature header to use. This is used to verify the authenticity of the request. Select X-HELPSCOUT-SIGNATURE if you are migrating from HelpScout.')" layout="row">
            <x-fruit::select id="signature_header" name="settings[customapp.signature_header]">
                @foreach (['X-FREESCOUT-SIGNATURE', 'X-HELPSCOUT-SIGNATURE'] as $signature_header)
                    <option value="{{ $signature_header }}" @selected($settings['customapp.signature_header'] == $signature_header)>{{ $signature_header }}</option>
                @endforeach
            </x-fruit::select>
        </x-fruit::field>

        <x-fruit::field label="Cache TTL" :description="__('Select the cache TTL to use. This is used to cache the response from the custom app.')" layout="row">
            <x-fruit::select id="cache_ttl" name="settings[customapp.cache_ttl]">
                @foreach (['0' => 'Disabled', '5' => '5 seconds', '10' => '10 seconds', '30' => '30 seconds', '60' => '1 minute', '300' => '5 minutes', '600' => '10 minutes', '900' => '15 minutes', '1800' => '30 minutes', '3600' => '1 hour'] as $cache_ttl => $cache_ttl_name)
                    <option value="{{ $cache_ttl }}" @selected($settings['customapp.cache_ttl'] == (string) $cache_ttl)>{{ $cache_ttl_name }}</option>
                @endforeach
            </x-fruit::select>
        </x-fruit::field>
    </x-fruit::form-section>

    <footer class="f-form-row settings-form__actions">
        <x-fruit::button type="submit" variant="primary">{{ __('Save') }}</x-fruit::button>
    </footer>
</form>
