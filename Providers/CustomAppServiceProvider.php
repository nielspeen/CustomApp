<?php

namespace Modules\CustomApp\Providers;

use Illuminate\Support\ServiceProvider;

class CustomAppServiceProvider extends ServiceProvider
{
    private const MODULE_NAME = 'customapp';

    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', self::MODULE_NAME);
        $this->hooks();
    }

    public function registerViews()
    {
        $viewPath = resource_path('views/modules/customapp');

        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/customapp';
        }, \Config::get('view.paths')), [$sourcePath]), 'customapp');
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath('customapp') . '/js/customapp.js';
            return $javascripts;
        });

        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath('customapp') . '/css/customapp.css';
            return $styles;
        });

        \Eventy::addAction('mailboxes.settings.menu', function ($mailbox) {
            if (auth()->user()->isAdmin()) {
                echo \View::make('customapp::partials/settings_menu', ['mailbox' => $mailbox])->render();
            }
        }, 34);

        // Settings view.
        \Eventy::addFilter('settings.view', function ($view, $section) {
            if ($section != 'customapp') {
                return $view;
            } else {
                return 'customapp::settings';
            }
        }, 20, 2);

        \Eventy::addAction('conversation.after_prev_convs', function ($customer, $conversation, $mailbox) {
            $url = \Option::get('customapp.callback_url')[(string)$mailbox->id] ?? '';
            $title = \Option::get('customapp.title')[(string)$mailbox->id] ?? 'Custom App';

            if ($url != '') {
                echo \View::make(self::MODULE_NAME . '::partials/sidebar', ['title' => $title])->render();
            }
        }, -1, 3);
    }
}
