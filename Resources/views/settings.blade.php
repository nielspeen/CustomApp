<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Title') }}</label>

        <div class="col-sm-6">
            <input type="text" class="form-control input-sized-lg" name="settings[customapp.title]" value="{{ $settings['customapp.title'] }}">

            <p class="form-help">
                {{ __('The title of the custom app. This is used to display the title of the custom app in the sidebar.') }}
            </p>
        </div>
    </div>

    <div class="form-group{{ $errors->has('settings.customapp->url') ? ' has-error' : '' }}">
        <label class="col-sm-2 control-label">{{ __('Callback URL') }}</label>

        <div class="col-sm-6">
            <div class="input-group input-sized-lg">
                <input type="text" class="form-control input-sized-lg" name="settings[customapp.callback_url]" value="{{ old('settings') ? old('settings')['customapp.callback_url'] : $settings['customapp.callback_url'] }}">
            </div>

            @include('partials/field_error', ['field'=>'settings.customapp->url'])

            <p class="form-help">
                {{ __('Example') }}: https://crm.example.org/api/freescout/callback
            </p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Secret Key') }}</label>

        <div class="col-sm-6">
            <input type="text" class="form-control input-sized-lg" name="settings[customapp.secret_key]" value="{{ $settings['customapp.secret_key'] }}">

            <p class="form-help">
                {{ __('The secret key used to generate a signature header. This can be used to verify the authenticity of the request.') }}
            </p>
        </div>
    </div>

    <div class="form-group">
        <label for="signature_header" class="col-sm-2 control-label">Signature Header</label>

        <div class="col-sm-6">
            <select id="signature_header" class="form-control input-sized" name="settings[customapp.signature_header]">
                <option value="X-FREESCOUT-SIGNATURE" {{ $settings['customapp.signature_header'] == 'X-FREESCOUT-SIGNATURE' ? 'selected' : '' }}>X-FREESCOUT-SIGNATURE</option>
                <option value="X-HELPSCOUT-SIGNATURE" {{ $settings['customapp.signature_header'] == 'X-HELPSCOUT-SIGNATURE' ? 'selected' : '' }}>X-HELPSCOUT-SIGNATURE</option>
            </select>

            <p class="form-help">
                {{ __('Select the signature header to use. This is used to verify the authenticity of the request. Select X-HELPSCOUT-SIGNATURE if you are migrating from HelpScout.') }}
            </p>

        </div>
    </div>

    <div class="form-group">
        <label for="signature_header" class="col-sm-2 control-label">Cache TTL</label>

        <div class="col-sm-6">
            <select id="cache_ttl" class="form-control input-sized" name="settings[customapp.cache_ttl]">
                <option value="0" {{ $settings['customapp.cache_ttl'] == '0' ? 'selected' : '' }}>Disabled</option>
                <option value="5" {{ $settings['customapp.cache_ttl'] == '5' ? 'selected' : '' }}>5 seconds</option>
                <option value="10" {{ $settings['customapp.cache_ttl'] == '10' ? 'selected' : '' }}>10 seconds</option>
                <option value="30" {{ $settings['customapp.cache_ttl'] == '30' ? 'selected' : '' }}>30 seconds</option>
                <option value="60" {{ $settings['customapp.cache_ttl'] == '60' ? 'selected' : '' }}>1 minute</option>
                <option value="300" {{ $settings['customapp.cache_ttl'] == '300' ? 'selected' : '' }}>5 minutes</option>
                <option value="600" {{ $settings['customapp.cache_ttl'] == '600' ? 'selected' : '' }}>10 minutes</option>
                <option value="900" {{ $settings['customapp.cache_ttl'] == '900' ? 'selected' : '' }}>15 minutes</option>
                <option value="1800" {{ $settings['customapp.cache_ttl'] == '1800' ? 'selected' : '' }}>30 minutes</option>
                <option value="3600" {{ $settings['customapp.cache_ttl'] == '3600' ? 'selected' : '' }}>1 hour</option>
            </select>

            <p class="form-help">
                {{ __('Select the cache TTL to use. This is used to cache the response from the custom app.') }}
            </p>

        </div>
    </div>


    <div class="form-group margin-top margin-bottom">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">
                {{ __('Save') }}
            </button>
        </div>
    </div>
</form>
