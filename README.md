# FreeScout Custom App 

* Similar to Helpscout's Legacy App feature.
* Fetches HTML from an external source to show in the sidebar of your conversations. 

## Customer matching

The existing callback can include customer details alongside `html`:

```json
{
    "html": "<p>Account details</p>",
    "customer": {
        "email": "primary@example.com",
        "emails": ["primary@example.com", "verified-alias@example.com"],
        "fname": "Ada",
        "lname": "Lovelace"
    }
}
```

Only return verified addresses in `customer.emails`: this list authorizes local
contact matching and merging. When it identifies one other contact, CustomApp
merges the current contact into that existing contact using FreeScout's merge
operation. Existing names are preserved, and modules receive the normal
`customer.merged` event (including Nostr's key transfer).

If multiple other contacts match, the current contact has an email outside the
verified list, or the agent cannot view the target, no automatic merge occurs.
Without a match, verified emails and missing names are filled in; npub placeholder
names can be replaced. Legacy `customer.email` responses still fill empty fields
but do not authorize merging.

This runs on the normal sidebar callback and uses the mailbox's existing cache
setting. No extra callback requests or background polling are added. A repeated
response does not repeat a completed merge. The response hook receives the
surviving contact and updated conversation association.

Modules can handle `customapp.response` to store callback metadata, then use the
`customapp.content` filter (`html`, `conversation`, `customer`, `mailbox`) to append
page data to the cached HTML. After inserting that HTML, the browser dispatches
`customapp:loaded` on `document`. Nostr uses these hooks to synchronize device
labels and refresh message headers without another callback request.

Run the integration tests from this module inside a FreeScout checkout:
`php Tests/customer_tests.php`. Fixtures use an isolated in-memory SQLite database;
Nostr key-transfer coverage runs when that module is installed.

# Limitations

* Allows for only 1 Custom App per Mailbox
