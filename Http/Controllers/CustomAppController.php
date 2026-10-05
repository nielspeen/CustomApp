<?php

namespace Modules\CustomApp\Http\Controllers;

use App\Mailbox;
use App\Conversation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class CustomAppController extends Controller
{
    public function mailboxSettings($id)
    {
        $mailbox = Mailbox::findOrFail($id);

        return view('customapp::mailbox_settings', [
            'settings' => [
                'customapp.callback_url' => \Option::get('customapp.callback_url')[(string)$id] ?? '',
                'customapp.secret_key' => \Option::get('customapp.secret_key')[(string)$id] ?? '',
                'customapp.signature_header' => \Option::get('customapp.signature_header')[(string)$id] ?? 'X-FREESCOUT-SIGNATURE',
                'customapp.title' => \Option::get('customapp.title')[(string)$id] ?? '',
                'customapp.cache_ttl' => \Option::get('customapp.cache_ttl')[(string)$id] ?? '0',
            ],
            'mailbox' => $mailbox
        ]);
    }

    public function mailboxSettingsSave($id, Request $request)
    {
        $settings = $request->settings ?: [];

        $urls = \Option::get('customapp.url') ?: [];
        $secrets = \Option::get('customapp.secret') ?: [];

        $urls[(string)$id] = $settings['customapp.callback_url'] ?? '';
        $secrets[(string)$id] = $settings['customapp.secret_key'] ?? '';
        $signatureHeaders[(string)$id] = $settings['customapp.signature_header'] ?? 'X-FREESCOUT-SIGNATURE';
        $titles[(string)$id] = $settings['customapp.title'] ?? '';
        $cacheTtls[(string)$id] = $settings['customapp.cache_ttl'] ?? '0';

        \Option::set('customapp.callback_url', $urls);
        \Option::set('customapp.secret_key', $secrets);
        \Option::set('customapp.signature_header', $signatureHeaders);
        \Option::set('customapp.title', $titles);
        \Option::set('customapp.cache_ttl', $cacheTtls);

        \Session::flash('flash_success_floating', __('Settings updated'));

        return redirect()->route('mailboxes.customapp', ['id' => $id]);
    }

    public function generateSignature(string $data, string $secret): string
    {
        return base64_encode(hash_hmac('sha1', $data, $secret, true));
    }

    public function content(Request $request)
    {
        if(!auth()->check()) {
            return response()->json(['status' => 'error', 'msg' => 'Unauthorized']);
        }

        $referrer = $request->headers->get('referer');

        if ($referrer) {
            $referrer = explode('?', $referrer)[0];
        }
        
        if (!is_array($referrerParts = explode('/', $referrer)) || !isset($referrerParts[4])) {
            return response()->json(['status' => 'error', 'msg' => 'Invalid referrer']);
        }

        $conversationId = $referrerParts[4] ?? null;

        if(!$conversation = Conversation::find($conversationId)) {
            return response()->json(['status' => 'error', 'msg' => 'Conversation not found']);
        }

        abort_unless(auth()->user()->can('view', $conversation), 403);

        if(!$mailbox = Mailbox::find($conversation->mailbox_id)) {
            return response()->json(['status' => 'error', 'msg' => 'Mailbox not found']);
        }

        $cacheTtl = \Option::get('customapp.cache_ttl')[(string)$mailbox->id] ?? '0';


        if($cacheTtl > 0 && Cache::has('customapp.conversation.' . $conversationId)) {
            return response(Cache::get('customapp.conversation.' . $conversationId), 200, [
                'Content-Type' => 'text/html',
            ]);
        }

        if(!$customer = $conversation->customer) {
            return response()->json(['status' => 'error', 'msg' => 'Customer not found']);
        }

        $callbackUrl = \Option::get('customapp.callback_url')[(string)$mailbox->id] ?? '';
        $secretKey = \Option::get('customapp.secret_key')[(string)$mailbox->id] ?? '';
        $signatureHeader = \Option::get('customapp.signature_header')[(string)$mailbox->id] ?? 'X-FREESCOUT-SIGNATURE';
        $title = \Option::get('customapp.title')[(string)$mailbox->id] ?? 'Custom App';


        if (!$callbackUrl) {
            return response()->json(['status' => 'error', 'msg' => 'Callback URL is not set']);
        }

        $payload = [
            'ticket' => [
                'id'        => $conversation->id,
                'number'    => $conversation->number,
                'subject'   => $conversation->subject,
            ],
            'customer' => [
                'id'        => $customer->id,
                'fname'     => $customer->first_name,
                'lname'     => $customer->last_name,
                'email'     => $customer->getMainEmail(),
                'emails'    => $customer->emails->pluck('email')->toArray(),
                'channel'   => $customer->channel ?? null,
                'channel_id' => $customer->channel_id ?? null,
            ],
            'mailbox' => [
                'id'            => $mailbox->id,
                'email'         => $mailbox->email,
            ]
        ];

        // Let other modules add data (e.g. the Nostr module adds the customer's public keys).
        $payload = \Eventy::filter('customapp.payload', $payload, $conversation, $customer, $mailbox);

        $content = json_encode($payload);
        $signature = $this->generateSignature($content, $secretKey);

        try {
            $client = app(\GuzzleHttp\Client::class);
            $result = $client->post($callbackUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/html',
                    $signatureHeader => $signature,
                ],
                'body' => $content,
            ]);
            $json = json_decode($result->getBody()->getContents(), true);
            // Data (sidebar, version 1) is rendered here; other callbacks send their own HTML.
            $sidebar = self::sidebarData($json['sidebar'] ?? null);
            $response = $sidebar
                ? view('customapp::partials/customer', ['title' => $title, 'sidebar' => $sidebar])->render()
                : view('customapp::partials/html', ['title' => $title, 'html' => $json['html'] ?? ''])->render();

            $customer = app(\Modules\CustomApp\Services\CustomerEnrichment::class)
                ->apply($conversation, $customer, $json['customer'] ?? []);
            if ($customer) {
                $conversation->customer_id = $customer->id;
                $conversation->setRelation('customer', $customer);
                \Eventy::action('customapp.response', $json, $conversation, $customer, $mailbox);
                $response = \Eventy::filter('customapp.content', $response, $conversation, $customer, $mailbox);
            }
        } catch (\Exception $e) {
            $response = view('customapp::partials/html', ['title' => $title, 'html' => e('Callback error: '.$e->getMessage())])->render();
        }

        if($cacheTtl > 0) {
            Cache::put('customapp.conversation.' . $conversationId, $response, $cacheTtl);
        }

        return response($response, 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * The callback's sidebar data (version 1), checked: it's remote, so only strings,
     * known tones and http(s) links, and a limited number of each. Null for other data.
     */
    public static function sidebarData($sidebar)
    {
        if (!is_array($sidebar) || ($sidebar['version'] ?? null) !== 1) {
            return null;
        }
        $text = fn ($value) => is_scalar($value) ? trim((string) $value) : '';
        $url = function ($value) use ($text) {
            $value = $text($value);

            return preg_match('#^https?://#i', $value) ? $value : '';
        };

        $sections = [];
        foreach (array_slice(is_array($sidebar['sections'] ?? null) ? $sidebar['sections'] : [], 0, 20) as $section) {
            $rows = [];
            foreach (array_slice(is_array($section['rows'] ?? null) ? $section['rows'] : [], 0, 50) as $row) {
                if (!is_array($row) || $text($row['label'] ?? '') === '') {
                    continue;
                }
                $links = [];
                foreach (array_slice(is_array($row['links'] ?? null) ? $row['links'] : [], 0, 20) as $link) {
                    if (is_array($link) && $url($link['url'] ?? '') && $text($link['text'] ?? '') !== '') {
                        $links[] = ['text' => $text($link['text']), 'url' => $url($link['url']), 'title' => $text($link['title'] ?? '')];
                    }
                }
                $rows[] = [
                    'label'  => $text($row['label']),
                    'url'    => $url($row['url'] ?? ''),
                    'value'  => $text($row['value'] ?? ''),
                    'tone'   => in_array($row['tone'] ?? '', ['warning', 'danger']) ? $row['tone'] : '',
                    'detail' => $text($row['detail'] ?? ''),
                    'links'  => $links,
                ];
            }
            if ($rows) {
                $sections[] = ['title' => $text($section['title'] ?? ''), 'collapsed' => !empty($section['collapsed']), 'rows' => $rows];
            }
        }

        return ['title' => $text($sidebar['title'] ?? ''), 'url' => $url($sidebar['url'] ?? ''), 'sections' => $sections];
    }
}
