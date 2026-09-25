<?php

// Run from an installed FreeScout checkout; all fixtures use an in-memory DB.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
set_exception_handler(function ($error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
});
config([
    'database.default' => 'customapp_test',
    'database.connections.customapp_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    'cache.default' => 'array',
    'session.driver' => 'array',
]);
if (\DB::connection()->getDriverName() !== 'sqlite' || \DB::connection()->getDatabaseName() !== ':memory:') {
    throw new \RuntimeException('Refusing to run outside the in-memory test database');
}

foreach (['customers', 'emails'] as $table) {
    $file = glob($root.'/database/migrations/*create_'.$table.'_table.php')[0];
    require_once $file;
    $class = 'Create'.ucfirst($table).'Table';
    (new $class())->up();
}
\Schema::table('customers', function ($table) {
    $table->text('notes')->nullable();
    $table->integer('channel')->nullable();
    $table->string('channel_id')->nullable();
    $table->text('meta')->nullable();
});
\Schema::create('conversations', function ($table) {
    $table->increments('id');
    $table->integer('customer_id');
    $table->integer('mailbox_id')->default(1);
    $table->timestamps();
});
\Schema::create('threads', function ($table) {
    $table->increments('id');
    $table->integer('customer_id');
    $table->integer('created_by_customer_id');
    $table->timestamps();
});
\Schema::create('customer_channel', function ($table) {
    $table->increments('id');
    $table->integer('customer_id');
    $table->integer('channel');
    $table->string('channel_id');
});
\Schema::create('mailboxes', function ($table) {
    $table->increments('id');
    $table->string('email');
});
\DB::table('mailboxes')->insert(['id' => 1, 'email' => 'support@example.com']);
\Schema::create('options', function ($table) {
    $table->increments('id');
    $table->string('name')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});

if (is_dir($root.'/Modules/Nostr')) {
    require_once $root.'/Modules/Nostr/Database/Migrations/2026_09_23_000002_create_nostr_customer_keys_table.php';
    require_once $root.'/Modules/Nostr/Database/Migrations/2026_09_23_000003_create_nostr_events_table.php';
    (new \CreateNostrCustomerKeysTable())->up();
    (new \CreateNostrEventsTable())->up();
    $app->register(\Modules\Nostr\Providers\NostrServiceProvider::class);
}

function check($condition, $message)
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function customer($name = 'npub1abc…def', $email = null)
{
    $customer = new \App\Customer(['first_name' => $name]);
    $customer->save();
    if ($email) {
        $customer->addEmail($email, true);
    }

    return $customer;
}

function conversation($customer)
{
    $id = \DB::table('conversations')->insertGetId(['customer_id' => $customer->id]);

    return \App\Conversation::find($id);
}

function enrich($conversation, $data, $original = null)
{
    return app(\Modules\CustomApp\Services\CustomerEnrichment::class)
        ->apply($conversation, $original ?: $conversation->customer, $data);
}

function runCase($name, $test)
{
    \Cache::flush();
    $user = new \App\User();
    $user->id = 1;
    $user->role = \App\User::ROLE_ADMIN;
    auth()->setUser($user);
    \DB::beginTransaction();
    try {
        $test();
        echo "ok   $name\n";
    } catch (\Throwable $error) {
        echo "FAIL $name: ".$error->getMessage()."\n";
        $GLOBALS['failures']++;
    } finally {
        \DB::rollBack();
    }
}
