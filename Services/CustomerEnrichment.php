<?php

namespace Modules\CustomApp\Services;

use App\Conversation;
use App\Customer;
use App\Email;
use Illuminate\Support\Facades\DB;

class CustomerEnrichment
{
    public function apply(Conversation $conversation, Customer $original, $data): ?Customer
    {
        if (!is_array($data) || !$data) {
            return $original;
        }

        // The list is the callback's verified identity evidence. Legacy single
        // email responses can still fill empty fields, but never merge contacts.
        $canMerge = isset($data['emails']) && is_array($data['emails']);
        $emails = $this->emails($data['emails'] ?? [$data['email'] ?? null]);

        return DB::transaction(function () use ($conversation, $original, $data, $canMerge, $emails) {
            $owners = Email::whereIn('email', $emails)->orderBy('id')->lockForUpdate()
                ->pluck('customer_id')->unique()->values()->all();
            $customers = Customer::whereIn('id', array_merge([$original->id], $owners))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = Conversation::where('id', $conversation->id)->lockForUpdate()->first();

            // Another sidebar request or a manual merge may have moved it while
            // the callback was in flight. Its old response must not move it again.
            if (!$current || $current->customer_id != $original->id) {
                return $current ? $current->customer : null;
            }
            $customer = $customers->get($original->id);
            if (!$customer) {
                return null;
            }

            $others = array_values(array_diff($owners, [$customer->id]));
            $existingEmails = $customer->emails()->pluck('email')->all();
            $canAttach = !$others && !array_diff($existingEmails, $emails);
            if ($canMerge && count($others) === 1 && !array_diff($existingEmails, $emails)) {
                $target = $customers->get($others[0]);
                if ($target && auth()->user()->can('view', $target) && auth()->user()->can('view', $customer)) {
                    $target->mergeWith($customer);
                    $customer = $target;
                    $canAttach = true;
                }
            }

            if ($canAttach) {
                foreach ($emails as $email) {
                    $customer->addEmail($email, true);
                }
            }
            $this->fillName($customer, $data);

            return $customer;
        }, 3);
    }

    private function emails($values): array
    {
        if (!is_array($values) || count($values) > 100) {
            return [];
        }
        $emails = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $email = mb_strtolower(trim($value), 'UTF-8');
            if (strlen($email) <= Email::MAX_LENGTH && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function fillName(Customer $customer, array $data): void
    {
        $placeholder = preg_match('/^npub1[a-z0-9]+…[a-z0-9]+$/u', $customer->first_name ?? '');
        foreach (['fname' => 'first_name', 'lname' => 'last_name'] as $key => $attribute) {
            if (($placeholder || !$customer->$attribute) && isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                $customer->$attribute = mb_substr(trim($data[$key]), 0, 255);
            }
        }
        if ($customer->isDirty()) {
            $customer->save();
        }
    }
}
