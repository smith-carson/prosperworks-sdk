---
layout: default
---

Introduction
============

At the time of implementation, there was no SDK for ProsperWorks, and we needed to do a bunch of operations to 
transfer data from our old CRM and synchronize information with the other subsystems, so we designed some classes to
encapsulate the API operations. It uses Guzzle, but ended up overgrowing and we turned into a standalone library.

This project was originally written by [igorsantos07] and is now maintained by [smith-carson]
([website](https://smithcarson.com)).

Installation
============

## 1. Install in your project
You need to be running **PHP 7** _(yep, it's stable since dec/2015 and packs a bunch of useful features, upgrade now!)_. 

To add it to your project through Composer, run `composer require igorsantos07/prosperworks`. It will get the most
stable version if your `min-requirements` are "stable" or `dev-master` otherwise.
 
## 2. Configure the package
There's a couple of things to configure to get the SDK running:

### API credentials
> Currently, there's no "API User" on ProsperWorks, so you need an active user to communicate with the API.
   
1. Sign in to ProsperWorks, go to **Settings > Preferences / API Keys**. There you will be able to generate a new API
   Key for the logged user. Copy that key, along with the user email.

2. Create a bootstrap file, or include the following code in the config section of your project: 
   `\ProsperWorks\Config::set($email, $token)`.
   
### Webhooks parameters
**[Optional]** If you're going to use Webhooks to get updates from ProsperWorks, you'll also need to feed in three
more arguments on that method:
1. A _Webhooks Secret_, that will be used to avoid unintended calls to your routes. That should be a plain string.
2. A _Root URL_. That's probably the same domain/path you use for your systems, and what ProsperWorks will POST to.
  More information on the [Webhooks](#webhooks) section.
3. A _Cryptography object_. It should respond to `encryptBase64()` and `decryptBase64()`, both receiving and returning a
   string (it can also implement `\ProsperWorks\Interfaces\Crypt` to make things easier). It will be used to send an
   encrypted secret, and decrypt it to make sure the call you receive comes from ProsperWorks (or, at least, someone
   that has the encrypted secret).

### Caching object
**[Optional]** To make some parts faster, you can also feed the sixth argument with a caching layer. It's an object that
needs to respond to `get()` and `save()`, or implement `\ProsperWorks\Interfaces\Cache`.

It's mainly used to cache (for an hour) meta-data from the API, such as Custom Fields, Activity and Contact Types, and
so on. That's information that rarely changes so it's safe to cache, making calls much faster (otherwise, for every
resource with custom fields we would need to retrieve from the custom fields endpoint as well).

## 3. Debug mode
During import scripts and similar tasks it could be useful to peek into the network traffic and see if what you intended
to do is being done correctly.
You can enable `echo`'s of debug information from the library by calling `ProsperWorks\Config::debugLevel()`:

- passing `ProsperWorks\Config::DEBUG_BASIC` will trigger some messages, such as "POST /people/search" so you know
 which requests are being sent. It also warns on Rate limits being hit;
- passing `ProsperWorks\Config::DEBUG_COMPLETE` does all above plus complete requests payload;
- passing null, false, 0 or `ProsperWorks\Config::DEBUG_NONE` will stop printing messages.

> This doesn't need to be done together with `Config::set()`; it can happen anywhere and will change behavior from that
part on.
   
### Tip: "sandbox" account
After a while, when implementing this library for the first time, we spoke with a support representative about the lack
of a sandbox environment. They suggested us to create a trial account and use that instead of a user on the paying 
account, and mention to the Support that was being used to test-drive the API implementation - and thus, they would
extend the trial of that solo account for as long as it was needed.

API Communication
=================
Most of the operations are done through the `\ProsperWorks\CRM` abstract class, and the resulting objects from it (you
can consider it some sort of Factory class). The exception are Webhooks, that have a special Endpoint class to make it
easier.

> Tip: **ProsperWorks API Documentation**  
You may want to read the [REST API Docs], to get an understanding of the inner pieces that make up this SDK.

With configurations in place, ProsperWorks API calls are done through a simple, fluent API. Most of the
endpoints behave the same way, with special cases being the Account and most meta-data endpoints.

> On the following examples we'll consider the classes were imported in the current namespace.

## Common endpoints
Singular, empty static calls to `CRM` give an `Endpoint` object (see [saving instances]), that allows you to run all
common operations:

```php
<?php
//runs GET /people/10 to retrieve a single record
$people = CRM::person()->find(10);

//runs GET /people multiple times (it's paged) until all entries are retrieved
$people = CRM::person()->all();
//there's no such operation in some endpoints; all() runs an empty /search, instead

//runs POST /people to generate a new record
$newPerson = CRM::person()->create(['name' => 'xxx']);

//runs PUT /people/25 to edit a given record
$person = CRM::person()->edit(25, ['name' => 'xxx']);

//runs DELETE /people/10 to destroy that record
$bool = CRM::person()->delete(10);

//runs POST /people/search with the given parameters until all entries are found (it's paged)
$people = CRM::person()->search(['email' => 'test@example.com']);
```

All success calls will return a `BareResource` object, with all information from that endpoint, or a list of those. See
[Response Objects](#response-objects) for details.  
If it fails, an error message is given. _(TODO: option to raise exceptions)_

There are also some shortcuts, such as:
```php
<?php
//plural calls do the same as the singular all() call
$people = CRM::people();       //same as CRM::person()->all()
$tasks = CRM::tasks();         //same as CRM::task()->all()
$companies = CRM::companies(); //same as CRM::company()->all()

//there's also two other types of magic calls
$people = CRM::person(23);                  //same as CRM::person()->find(23)
$people = CRM::person(['country' => 'US']); //same as CRM::person()->search(...)
```

## Special cases: restricted endpoints

All meta-data resources (called _Secondary Resources_ on the docs), together with the `Account` endpoint, have only
read access. There's no verification of valid operations yet (see [#7](issue-7)). Here's a list of those read-only
endpoints, accessible through the plural call (e.g. `CRM::activityTypes()`), except for `Account` which is singular:

- Account (the only one you have to call in the singular)
- Activity Types
- Contact Types
- Custom Fields
- Customer Sources
- Loss Reasons
- Pipelines
- Pipeline Stages

### Meta-data shortcuts

As those endpoints are mostly lists, you can also access that data through the cacheable `CRM::fieldList()` method,
which returns the information in a more organized fashion:
```php
<?php
$types = CRM::fieldList('contactType'); //singular!
print_r($types);
// gives an array of names, indexed by ID:
// (
//     [123] => Potential Customer
//     [124] => Current Customer
//     [125] => Uncategorized
//     [126] => Former Customer
// )

echo CRM::fieldList('contactType', 524131); //search argument
// prints "Potential Customer". That argument searches on both sides of the array

$actTypes = CRM::fieldList('activityType', null, true); //asks for "detailed response"
print_r($actTypes);
// gives the entire resources, still indexed by ID
//     [166] => stdClass Object
//         (
//             [id] => 166
//             [category] => user
//             [name] => Social Media 
//             [is_disabled] => 
//             [count_as_interaction] => 1
//      )
//
//     [...]
// )
```

> **Sanity warning:** those IDs there are samples; they're different for each ProsperWorks customer.

It's also worth noting that some fields are "translated" from the API into specific objects, such as timestamps,
addresses, Custom Fields and more, so you'll probably never have to deal with the Custom Fields endpoint directly.
More information about that on the [SubResources](#subresources) and [Response Objects](#response-objects) sections. 

## Related Items
There's an unified API to created links between two resources. Thus, every Resource object has its own `related` method,
to manipulate those links. As that's a very simple API, you can only list, create and delete relationships. Take a look
on the [Documentation for Related Items] to see the relation limits - some resources allow for only one link, and not
every resource has relationships with every other.

```php
<?php
// you always have to feed the origin resource ID to related()
// and then call the operation you want, like:
$tasks = CRM::task()->related(10)->all();              //lists all
$task_projects = CRM::task()->related(22)->projects(); //lists specific type
$task_project = CRM::task()->related(22)->create(10, 'project'); //create one
$task_project = CRM::task()->related(22)->delete(27, 'project'); //and remove
```

## Batch Operations
It's also possible to run batch operations, using Guzzle's concurrency features to speed up with parallel calls.
Some single-usage methods have a *Many counterpart, such as:
- ` createMany()`: straightforward; instead of a payload, you pass a list of;
- `editMany()`: in this case, you got to pass a list of payloads, indexed by IDs.
- `delete()` is special, as it can handle an arbitrary number of IDs. Its response will vary on the number of arguments.
 
You can use an array, Interator or [Generator] on these, and it will take care to run as much as 10 _(future: configurable)_ HTTP calls at the same time.

As an example, let's create a lot of Task entries, based on a query result (that also has low memory usage), and then
remove these:
```php
<?php
//this call is using a simple generator
$thousandsOfTasksQueryResult = [...];
$allTasks = CRM::task()->createMany(function() use ($thousandsOfTasksQueryResult) {
    foreach ($thousandsOfTasksQueryResult as $task) {
        yield [
            'name' => $task->name,
            'due_date' => $task->dueDate->format('U'),
            'status' => $task->completed? 'Completed' : 'Open'
        ];
    }
});

// as that's a batch operation, it seemed unsafe to throw harsh errors.
// thus, success will give an object of data, while errors return a simple message
$toDelete = [];
foreach ($allTasks as $response) {
    if (is_object($response) {
        $toDelete[] = $response->id;
    } else {
        $logger->warning("Couldn't create Task: $response");
    }
}

//here we use a plain list of arguments: you have to unpack the array
CRM::task()->delete(...$toDelete);
}
```

A [generator] is specially useful in these cases as it will save you a lot of memory, by not storing a long list of
payloads/requests in-memory.

### Batch Relationship operations
Similar to batch API calls, it's also possible to run a bunch of relation changes. To do that, use `relatedBatch()`'s
methods, with a list of ID + Type (or the `Relation` helper object), indexed by origin ID:

```php
<?php
use ProsperWorks\SubResources\Relation;

$relClientsQuery = [...];
CRM::task()->relatedBatch()->create(function() use ($relClientsQuery, $pwTaskId) {
    foreach ($relatedClientsQuery as $client) {
        // this would generate an array of Relation() objects, indexed by the same ID
        // causes no error; this won't become a real array (thus, with keys conflicts)
        yield new $pwTaskId => new Relation($client->id, 'company');

        // the following would also work
        //yield new $pwTaskId => ['id' => $client->id, 'type' => 'company'];
    }
});
```

## I don't think all those static calls are performant
Indeed, on a very small scale, they might not be. You can always use the half-way object to run common operations, as
when you're running a bunch of operations on the same endpoint. However, the static calls will save you from a couple
of config/instances on one-off calls ;)

```php
<?php
$peopleRes = CRM::person();
$client = $peopleRes->find($clientId);
$tags = array_merge($client->tags, 'new tag');
$peopleRes->edit($clientId, compact('tags'));
```

## Rate limiting
There's also a RateLimit blocker built-in to the SDK, so it will `sleep()` a bit when it notices a limit would be hit,
allowing for new operations shortly after the limit is released. That emits some notices on the CLI when
[Debug mode](#3-debug-mode) is on. This is specially useful for [Batch operations](#batch-operations).

## Response objects
Most (all?) responses will be a `BareResource` object, or a list of those. The biggest advantage is that class's
"translation" capabilities: it makes some parts of the payload easier to use by leveraging Objects with simpler /
predictable structures, or with some data validation / translation under the hood (AKA [SubResources](#subresources)).

- most date fields (date_created, due_date, date_last_contacted, ...) will turn from UNIX Timestamps into `DateTime`
  objects. _There's a not-really-working setting to disable that, on BareResource (see [#8](issue-8))_
- a `contact_type` field will be generated with the name related to `contact_type_id`, if any
- [Custom Fields](#custom-field) will generate two entries:
  - `custom_fields_raw`, containing the original payload from the API
  - `custom_fields`, containing a list of `CustomField` objects, indexed by field name
- some other complex structures will also become SubResource objects, such as:
  - address: [`Address`](#address)
  - socials, websites: [`URL[]`](#url)
  - phone_numbers: [`Phone[]`](#phone)


## SubResources
There are a couple of dependant objects that are not to be used directly on API calls, but make part of the main
resources. Most of the times, those are inner documents inside the JSON payload. They're used on responses (see
[Response Objects](#response-objects)), but they're also designed to make your calls easier, "translating" some
information back and forth, and making sure you always follow the requested rules for those sub-documents.

In special, a SubResource implementing the `TranslateResource` trait will allow read-access to some protected
fields (listed in `$altFields`). In short, when you turn an object into an array on PHP (what we do to get the final
JSON payload) it creates an array of all public fields. Thus, a `TranslateResource` is able to give read access to some
"hidden" properties while not exposing that to the API. See the list below for behavior examples:

### Address
Accepts as the first argument either a complete line (a string called `street`, because that's how ProsperWorks does),
or an array with two address lines (called `address` and `suite`). To change between those two formats a there's a
"suite" separator (hint: if there's a "suite" on the suite part already, it won't be repeated ok?). The other arguments
are pretty standard, such as city, postal code and so on.

```php
<?php
$sherlock = new Address('221B Baker St. suite 2', 'London');
// same as  new Address(['221B Baker St.', '2'], 'London');
// same as  new Address(['221B Baker St.', 'suite 2'], 'London');
echo $sherlock->street;  //'221B Baker St. suite 2'
echo $sherlock->address; //'221B Baker St.'
echo $sherlock->suite;   //'2'

$nemo = new Address('42 Wallaby Way', 'Sydney');
echo $nemo->street;  //'42 Wallaby Way'
echo $nemo->address; //'42 Wallaby Way'
echo $nemo->suite;   //null

//and then, use at will:
CRM::person()->create([
    'name' => 'P. Sherman',
    'address' => $nemo
]);
```

### Relation
This was [shown before](#batch-relationship-operations): it's a simple holder for `id` and `type`, no extra features.

### Custom Field
This is the biggest guy. It does the translation between a bare custom field specification (id + value, not really
meaningful for humans) and actually readable information. The original field ID (same ID for the same field, even
across different types of resources) is stored in `custom_field_definition_id`, together with the `value`. There's
always a read-only `name` property, with the field's actual name, and if it's a list, a read-only string `valueName`
will also be filled with the field's readable value.

To create a Custom Field entry, you can use both the field ID or field name as the first argument, and either the value
ID or string on the second place. Remember to double-check casing when you use a string instead of the IDs, though!

The following example displays both how to use the class and how it's returned in the SDK responses:
```php
<?php
$person = CRM::person()->create([
    'name' => 'John Doe',
    'custom_fields' => [
        new CustomField('Alias', 'Johnny Doe')
    ]
]);

print_r($person);
// ProsperWorks\Resources\BareResource Object (
//     [id] => 12340904
//     [name] => John Doe
//     [custom_fields] => Array (
//         [Alias] => ProsperWorks\SubResources\CustomField Object (
//             [custom_field_definition_id] => 128903
//             [value] => Johnny Doe
//             [name:protected] => Alias
//             [valueName:protected] =>
//             [...]
//         )
//     )
//     [custom_fields_raw] => Array (
//         [0] => stdClass Object (
//             [custom_field_definition_id] => 128903
//             [value] => Johnny Doe
//         )
//         [1] => stdClass Object (
//             [custom_field_definition_id] => 124953
//             [value] =>
//         )
//     )
//     [...]
// )
```

### "Categorized" sub-resources
All of these classes inherit from `Categorized`, giving them a `category` property and a couple of constants to use as
values. If, on a child class, a constant is marked as "deprecated", it means it doesn't really work with that object.

Their signature is the same: value as first argument, category as the second one.

#### Email
Simplest child: has an `email` property, together with the `category`.

#### Phone
The first argument is the string `number`. But the trick here is you can feed an extension by using something like this:
"123-4444 x123", and it will get separated into two read-only fields: `simpleNumber` and `extension`.

#### URL
Feed a valid `url` as the first argument and a social URL `category` will be automatically filled, if any matches.
For other cases, you can give the category manually as the second argument, as usual.

Webhooks
========
On the other hand, if you want to get updates from ProsperWorks, you have to setup _your_ endpoints for them to call
with any changes that happen.

> Tip: you may want to take a look on [Webhooks guide]; it's still being worked on - that's why it's still a KB article.

## Available Events
According to the documentation, there are three types of events that you can subscribe to:
<dl>
    <dt><code>Webhooks::EV_NEW</code></dt>     <dd>a new entry was created</dd>
    <dt><code>Webhooks::EV_UPDATE</code></dt>  <dd>an entry got updated</dd>
    <dt><code>Webhooks::EV_DELETE</code></dt>  <dd>an entry got deleted</dd>
    <dt><code>Webhooks::EV_ALL</code></dt>     <dd>catch-all constant that will subscribe you to all events at once</dd>
</dl>

And these are available for any of the main endpoints, listed under `Webhooks::ENDPOINTS`:
- Company
- Lead
- Opportunity
- Person
- Project
- Task

## How to interact with the webhooks
There are a couple of methods on the `Webhooks` class, that you should use on your project's [REPL] or CLI tool when you
configure them, or inside your Controller to manipulate the ProsperWorks calls:

### To configure webhooks
You must first instantiate the `Webhooks` object with the root path for your application's environment, otherwise it
will use what's configured as default (if any) by `Config`. That in-place config ability will come in handy during
[testing](#how-to-develop-with-webhooks).

Then, you can use a couple of methods to interact with the ProsperWorks Webhooks API:
<dl>
    <dt><code>list(int $id = null)</code></dt>
         <dd>Returns a list of webhook details, indexed by ID.</dd>
    <dt><code>create(string $endpoint, $event = self::ALL)</code></dt>
        <dd>Subscribes a new webhook for the given endpoint and event match, with the secret specified on `Config`. You
            should use one of the CRM::RES_* constants for the first argument.</dd>
    <dt><code>delete(int ...$id)</code></dt>
        <dd>Removes one or more webhooks from the ProsperWorks pool.</dd>
</dl>

### To interpret webhook calls
Every time a set event happens on one of the set endpoints, ProsperWorks's servers will make an HTTPS call to an address
that is composed like this: `<root_path>/prosperworks_hooks/<endpoint>/<event>`. Your controllers should somehow be able
to interpret that the way best suits your application, but most of that information is also repeated in the payload, so
feel free to have a single catch-all action to work on it.

#### The webhook payload
The payload is plain and simple. It contains the affected/generated IDs (usually a single one, unless that's a batch
operation), the affected endpoint and generated event, the webhook subscription ID, a timestamp and our secret.

Before any further operation, you should verify, for security purposes, if the secret is correct. To do that, call
`validateSecret()` with the payload, in array format. It will search for the secret, decrypt and verify it. Returns null
when the secret is not present, or false if it's there but isn't valid.

The next step, usually, would be for you to access the ProsperWorks API as usual to gather information on the created / 
updated resource, and update your database accordingly, or remove whatever needs to be removed.
  
> It's worth saying that, with their current UI, every field change made by the user is automatically saved; that's good
for their users, but every of those save calls will make a new webhook call, and that might overwhelm your server.   
> Thus, a nice idea would be to have some sort of **queue system** to work on those changes, and maybe even a
**deboncer** that could reduce repeated changes on the same resources - i.e. discarding payloads with the same endpoint,
event and IDs that happened in a short period of time.

## How to develop with webhooks
An important fact here is that ProsperWorks demands all webhook calls to be encrypted, what means you must provide an
HTTPS address for them. Besides that, your webserver must be openly accessible on the web. That might not be the most
trivial settings for a development environment, right?

Well, the tip is using [ngrok](http://ngrok.io) during development. You can run it pretty easily from the command-line
and have it open a stable HTTPS tunnel, and give you it's URL; you feed that into the `Webhooks` object and setup new
subscriptions that ProsperWorks can call when you make changes on their UI.  
It's also possible to inspect the HTTP traffic using the Ngrok inspector; its URL is also displayed when you run it.
That might come in handy so you don't need to edit fields a thousand of times to verify your code is working, as it's
able to store and repeat calls it received. Neat, isn't it?



[igorsantos07]: https://github.com/igorsantos07
[smith-carson]: https://github.com/smith-carson
[REST API Docs]: https://www.prosperworks.com/developer_api
[Webhook Guide]: https://prosperworks.zendesk.com/hc/en-us/articles/217214766-ProsperWorks-Webhooks
[saving instances]: #i-dont-think-all-those-static-calls-are-performant
[Documentation for Related Items]: https://www.prosperworks.com/developer_api/related_items
[Generator]: http://php.net/manual/en/language.generators.overview.php
[REPL]: https://en.wikipedia.org/wiki/Read%E2%80%93eval%E2%80%93print_loop

[issue-7]: https://github.com/smith-carson/prosperworks-sdk/issues/7
[issue-8]: https://github.com/smith-carson/prosperworks-sdk/issues/8