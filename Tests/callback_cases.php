<?php

runCase('sidebar merges from one callback and cached views make no more requests', function () {
    $target = customer('Existing', 'existing@example.com');
    $source = customer();
    $conversation = conversation($source);
    \Option::set('customapp.callback_url', ['1' => 'https://callback.example.test']);
    \Option::set('customapp.secret_key', ['1' => 'test-secret']);
    \Option::set('customapp.signature_header', ['1' => 'X-HelpScout-Signature']);
    \Option::set('customapp.cache_ttl', ['1' => '5']);
    $history = [];
    $mock = new \GuzzleHttp\Handler\MockHandler([
        new \GuzzleHttp\Psr7\Response(200, [], json_encode(['html' => '<b>Sidebar</b>', 'customer' => ['emails' => ['existing@example.com']]])),
    ]);
    $stack = \GuzzleHttp\HandlerStack::create($mock);
    $stack->push(\GuzzleHttp\Middleware::history($history));
    app()->instance(\GuzzleHttp\Client::class, new \GuzzleHttp\Client(['handler' => $stack]));
    $request = \Illuminate\Http\Request::create('/customapp/content');
    $request->headers->set('referer', 'https://help.example.test/conversation/'.$conversation->id);
    $controller = app(\Modules\CustomApp\Http\Controllers\CustomAppController::class);
    $observed = null;
    $hook = function ($json, $conversation, $customer) use (&$observed) {
        $observed = [$conversation->customer_id, $conversation->customer->id, $customer->id];
    };
    \Eventy::addAction('customapp.response', $hook, 20, 3);
    try {
        $first = $controller->content($request);
        $second = $controller->content($request);
        check(str_contains($first->getContent(), '<b>Sidebar</b>') && $second->getContent() === $first->getContent(), $first->getContent());
        check(count($history) === 1, 'extra callback request');
        check($observed === [$target->id, $target->id, $target->id], 'response hook received a deleted contact');
        $sent = $history[0]['request'];
        check($sent->getHeaderLine('X-HelpScout-Signature') === base64_encode(hash_hmac('sha1', (string) $sent->getBody(), 'test-secret', true)), 'callback signature changed');
    } finally {
        \Eventy::removeAction('customapp.response', $hook, 20);
    }
});

runCase('a cached sidebar cannot bypass conversation access', function () {
    $source = customer();
    $conversation = conversation($source);
    \Cache::put('customapp.conversation.'.$conversation->id, '<b>Private</b>', 5);
    $user = new class extends \App\User {
        public function can($abilities, $arguments = []) { return false; }
    };
    auth()->setUser($user);
    $request = \Illuminate\Http\Request::create('/customapp/content');
    $request->headers->set('referer', 'https://help.example.test/conversation/'.$conversation->id);
    try {
        app(\Modules\CustomApp\Http\Controllers\CustomAppController::class)->content($request);
        throw new \RuntimeException('private sidebar was returned');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
        check($error->getStatusCode() === 403, 'wrong access denial');
    }
});

runCase('sidebar data is rendered as inspector sections, safely', function () {
    $json = json_decode(file_get_contents(__DIR__.'/fixtures/sidebar_v1.json'), true);
    $sidebar = \Modules\CustomApp\Http\Controllers\CustomAppController::sidebarData($json['sidebar']);
    check(count($sidebar['sections']) === 2, 'an empty section was kept');
    check(count($sidebar['sections'][0]['rows'][1]['links']) === 1, 'a javascript: link was kept');
    check($sidebar['sections'][0]['rows'][2]['url'] === '', 'a javascript: url was kept');
    check($sidebar['sections'][0]['rows'][0]['tone'] === '', 'success should be plain text');
    check(\Modules\CustomApp\Http\Controllers\CustomAppController::sidebarData(['version' => 2]) === null, 'unknown version accepted');

    $html = view('customapp::partials/customer', ['title' => '12VPX', 'sidebar' => $sidebar])->render();
    check(str_contains($html, '<h3>12VPX</h3>') && str_contains($html, 'href="https://12vpx.example/backend/clients/123" target="_blank" rel="noopener">Leman Lee</a>'), 'customer heading');
    check(str_contains($html, '<h3>Account</h3>') && !str_contains($html, 'Help Scout'), 'sections');
    check(str_contains($html, 'f-badge--warning') && substr_count($html, 'f-badge--') === 1, 'badges only for warnings');
    check(!str_contains($html, '<script>') && !str_contains($html, '<b>bold</b>') && !str_contains($html, 'javascript:'), 'unescaped remote data');
    check(str_contains($html, 'Speed tests · 2') && str_contains($html, '<details'), 'collapsed section');
});

