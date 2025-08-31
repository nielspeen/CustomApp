<?php

namespace Modules\CustomApp\Http\Controllers;

use App\Mailbox;
use App\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                'customapp.cache_ttl' => \Option::get('customapp.cache_ttl')[(string)$id] ?? '30',
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
        $cacheTtls[(string)$id] = $settings['customapp.cache_ttl'] ?? '30';

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

        if(Cache::has('customapp.conversation.' . $conversationId)) {
            return response(Cache::get('customapp.conversation.' . $conversationId), 200, [
                'Content-Type' => 'text/html',
            ]);
        }

        if(!$conversation = Conversation::find($conversationId)) {
            return response()->json(['status' => 'error', 'msg' => 'Conversation not found']);
        }

        if(!$mailbox = Mailbox::find($conversation->mailbox_id)) {
            return response()->json(['status' => 'error', 'msg' => 'Mailbox not found']);
        }

        if(!$customer = $conversation->customer) {
            return response()->json(['status' => 'error', 'msg' => 'Customer not found']);
        }

        $callbackUrl = \Option::get('customapp.callback_url')[(string)$mailbox->id] ?? '';
        $secretKey = \Option::get('customapp.secret_key')[(string)$mailbox->id] ?? '';
        $signatureHeader = \Option::get('customapp.signature_header')[(string)$mailbox->id] ?? 'X-FREESCOUT-SIGNATURE';
        $title = \Option::get('customapp.title')[(string)$mailbox->id] ?? 'Custom App';
        $cacheTtl = \Option::get('customapp.cache_ttl')[(string)$mailbox->id] ?? '30';

        if (!$callbackUrl) {
            return response()->json(['status' => 'error', 'msg' => 'Callback URL is not set']);
        }

        $payload = [
            'customer' => [
                'id'        => $customer->id,
                'email'     => $customer->getMainEmail(),
                'emails'    => $customer->emails->pluck('email')->toArray(),
            ]
        ];

        $content = json_encode($payload);
        $signature = $this->generateSignature($content, $secretKey);

        try {
            $client = new \GuzzleHttp\Client();
            $result = $client->post($callbackUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/html',
                    $signatureHeader => $signature,
                ],
                'body' => $content,
            ]);
            $response = json_decode($result->getBody()->getContents(), true)['html'];
        } catch (\Exception $e) {
            $response = 'Callback error: ' . $e->getMessage();
        }

        if($cacheTtl > 0) {
            Cache::put('customapp.conversation.' . $conversationId, $response, $cacheTtl);
        }

        return response($response, 200, [
            'Content-Type' => 'text/html',
        ]);
    }
}
