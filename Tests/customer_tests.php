<?php

require __DIR__.'/bootstrap.php';
$failures = 0;

runCase('matches any verified address and preserves the existing contact', function () {
    $existing = customer('Existing name', 'alias@example.com');
    $source = customer();
    $conversation = conversation($source);
    $secondConversation = conversation($source);
    \DB::table('threads')->insert(['customer_id' => $source->id, 'created_by_customer_id' => $source->id]);
    $result = enrich($conversation, ['emails' => ['primary@example.com', ' ALIAS@EXAMPLE.COM '], 'fname' => 'Callback name']);
    check($result->id === $existing->id && $result->first_name === 'Existing name', 'wrong survivor or overwritten name');
    check(!\App\Customer::find($source->id), 'source still exists');
    check($secondConversation->fresh()->customer_id === $existing->id, 'conversation not transferred');
    check(\DB::table('threads')->where('customer_id', $existing->id)->where('created_by_customer_id', $existing->id)->count() === 1, 'thread authors not transferred');
    check(\App\Email::where('customer_id', $existing->id)->count() === 2, 'emails not retained');
    check(enrich($conversation, ['emails' => ['elsewhere@example.com']], $source)->id === $existing->id, 'stale request did not resolve the survivor');
    check(!\App\Email::where('email', 'elsewhere@example.com')->exists(), 'stale callback changed the survivor');
});

runCase('fills a new contact and repeated responses change nothing', function () {
    $source = customer();
    $conversation = conversation($source);
    $data = ['emails' => ['new@example.com', 'NEW@example.com'], 'fname' => 'Ada', 'lname' => 'Lovelace'];
    $result = enrich($conversation, $data);
    check($result->first_name === 'Ada' && $result->last_name === 'Lovelace', 'name not applied');
    \DB::connection()->enableQueryLog();
    \DB::connection()->flushQueryLog();
    enrich($conversation, $data);
    $writes = array_filter(\DB::getQueryLog(), function ($query) {
        return preg_match('/^(insert|update|delete)/i', $query['query']);
    });
    \DB::connection()->disableQueryLog();
    check(!$writes && \App\Email::where('customer_id', $source->id)->count() === 1, 'repeat mutated the contact');
});

runCase('does not choose between two existing contacts', function () {
    $a = customer('A', 'a@example.com');
    $b = customer('B', 'b@example.com');
    $source = customer();
    $result = enrich(conversation($source), ['emails' => ['a@example.com', 'b@example.com', 'new@example.com'], 'fname' => 'Ada']);
    check($result->id === $source->id && \App\Customer::count() === 3, 'ambiguous merge');
    check(!\App\Email::where('email', 'new@example.com')->exists(), 'ambiguous response claimed another address');
});

runCase('does not merge or attach addresses to an unrelated established contact', function () {
    $existing = customer('Existing', 'match@example.com');
    $source = customer('Staff edited', 'other@example.com');
    $conversation = conversation($source);
    enrich($conversation, ['emails' => ['match@example.com'], 'fname' => 'Changed']);
    enrich($conversation, ['emails' => ['unowned@example.com'], 'fname' => 'Changed']);
    check($source->fresh()->first_name === 'Staff edited' && \App\Customer::count() === 2, 'established contact changed');
    check(\App\Email::where('customer_id', $source->id)->count() === 1, 'unrelated email attached');
});

runCase('merges contacts already enriched by an earlier callback', function () {
    $target = customer('Existing', 'alias@example.com');
    $source = customer('Ada', 'primary@example.com');
    $result = enrich(conversation($source), ['emails' => ['primary@example.com', 'alias@example.com']]);
    check($result->id === $target->id && \App\Email::where('customer_id', $target->id)->count() === 2, 'earlier email prevented merge');
});

runCase('legacy responses fill empty fields but cannot merge', function () {
    $existing = customer('Existing', 'existing@example.com');
    $source = customer();
    $conversation = conversation($source);
    enrich($conversation, ['email' => 'existing@example.com', 'fname' => 'Ada']);
    check(\App\Customer::count() === 2 && !$source->fresh()->getMainEmail(), 'legacy response merged');
    enrich($conversation, ['email' => 'free@example.com', 'lname' => 'Lovelace']);
    check($source->fresh()->last_name === 'Lovelace' && \App\Email::where('email', 'free@example.com')->exists(), 'legacy enrichment stopped working');
});

runCase('does not merge into a contact the agent cannot view', function () {
    $target = customer('Hidden', 'hidden@example.com');
    $source = customer();
    $actor = new class extends \App\User {
        public function can($abilities, $arguments = []) { return false; }
    };
    auth()->setUser($actor);
    $result = enrich(conversation($source), ['emails' => ['hidden@example.com']]);
    check($result->id === $source->id && \App\Customer::count() === 2, 'inaccessible contact merged');
});

runCase('merge hook failure rolls back every transfer', function () {
    $existing = customer('Existing', 'existing@example.com');
    $source = customer();
    $conversation = conversation($source);
    $hook = function () { throw new \RuntimeException('test merge failure'); };
    \Eventy::addAction('customer.merged', $hook, 99, 3);
    try {
        enrich($conversation, ['emails' => ['existing@example.com']]);
        throw new \RuntimeException('merge unexpectedly succeeded');
    } catch (\RuntimeException $error) {
        check($error->getMessage() === 'test merge failure', $error->getMessage());
    } finally {
        \Eventy::removeAction('customer.merged', $hook, 99);
    }
    check(\App\Customer::find($source->id) && $conversation->fresh()->customer_id === $source->id, 'failed merge partially persisted');
});

if (class_exists(\Modules\Nostr\Entities\CustomerKey::class)) {
    runCase('Nostr keys and future messages follow the surviving contact', function () {
        $target = customer('Existing', 'existing@example.com');
        $source = customer();
        $pubkey = str_repeat('a', 64);
        \Modules\Nostr\Entities\CustomerKey::link($source, $pubkey);
        enrich(conversation($source), ['emails' => ['existing@example.com']]);
        $handler = new \Modules\Nostr\Services\IncomingMessageHandler();
        list($found, $created) = $handler->findOrCreateCustomer($pubkey);
        check($found->id === $target->id && !$created, 'future Nostr message created another contact');
    });
}

require __DIR__.'/callback_cases.php';
exit($failures ? 1 : 0);
