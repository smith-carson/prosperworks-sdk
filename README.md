ProsperWorks (unofficial) PHP SDK
=================================

At the time of implementation, there was no SDK for ProsperWorks, and we needed to do a bunch of operations to 
transfer data from our old CRM and synchronize information with the other subsystems, so we designed some classes to
encapsulate the API operations. It uses Guzzle, but ended up overgrowing and we turned into a standalone library.

Installation
------------

### 1. Install in your project
You need to be running **PHP 7** _(yep, it's stable since dec/2015 and packs a bunch of useful features, upgrade now!)_. 

To add it to your project through Composer, run `composer require igorsantos07/prosperworks`. It will get the most
stable version if your `min-requirements` are "stable" or `dev-master` otherwise.
 
### 2. Configure the package
There's a couple of things to configure to get the SDK running:

#### API credentials  
   > Currently, there's no "API User" on ProsperWorks, so you need an active user to communicate with the API.
   
1. Sign in to ProsperWorks, go to **Settings > Preferences / API Keys**. There you will be able to generate a new API
   Key for the logged user. Copy that key, along with the user email.

2. Create a bootstrap file, or include the following code in the config section of your project: 
   `\ProsperWorks\Config::set($email, $token)`.
   
#### Webhooks parameters
**[Optional]** If you're going to use Webhooks to get updates from ProsperWorks, you'll also need to feed in three
more arguments on that method:
1. A _Webhooks Secret_, that will be used to avoid unintended calls to your routes. That should be a plain string.
2. A _Root URL_. That's probably the same domain/path you use for your systems, and what ProsperWorks will POST to.
  More information on the [Webhooks](#webhooks) section.
3. A _Cryptography object_. It should respond to `encryptBase64()` and `decryptBase64()`, both receiving and returning a
   string (it can also implement `\ProsperWorks\Interfaces\Crypt` to make things easier). It will be used to send an
   encrypted secret, and decrypt it to make sure the call you receive comes from ProsperWorks (or, at least, someone
   that has the encrypted secret).

#### Caching object
**[Optional]** To make some parts faster, you can also feed the sixth argument with a caching layer. It's an object that
needs to respond to `get()` and `save()`, or implement `\ProsperWorks\Interfaces\Cache`.

It's mainly used to cache (for an hour) meta-data from the API, such as Custom Fields, Activity and Contact Types, and
so on. That's information that rarely changes so it's safe to cache, making calls much faster (otherwise, for every
resource with custom fields we would need to retrieve from the custom fields endpoint as well).

### 3. Debug mode
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

Usage
-----
Most of the operations are done through the `\ProsperWorks\CRM` abstract class, and the resulting objects from it (you
can consider it some sort of Factory class). The exception are Webhooks, that have a special Endpoint class to make it
easier.

> On the following examples we'll consider the classes were imported in the current namespace. 

### API Communication
With configurations in place, ProsperWorks API calls are done through a simple, fluent API. Most of the
endpoints behave the same way, with special cases being the Account and most meta-data endpoints.

#### Common endpoints
Singular, empty static calls to `CRM` yield an `Endpoint` object, that allows you to run all common operations:

```php
//runs GET /people/10 to retrieve a single record
$people = CRM::person()->find(10);

//runs GET /people multiple times (it's paged) until all entries are retrieved
$people = CRM::person()->all();
//there's no such operation in some endpoints; all() runs an empty /search, instead

//runs POST /people to generate a new record
$people = CRM::person()->create(['name' => 'xxx']);

//runs PUT /people/25 to edit a given record
$people = CRM::person()->edit(25, ['name' => 'xxx']);

//runs DELETE /people/10 to destroy that record
$people = CRM::person()->delete(10);

//runs POST /people/search with the given parameters until all entries are found (it's paged)
$people = CRM::person()->search(['email' => 'test@example.com']);
```

There are also some shortcuts, such as:
```php
//plural calls do the same as the singular all() call
$people = CRM::people();       //same as CRM::person()->all()
$tasks = CRM::tasks();         //same as CRM::task()->all()
$companies = CRM::companies(); //same as CRM::company()->all()

//there's also two other types of magic calls
$people = CRM::person(23);                  //same as CRM::person()->find(23)
$people = CRM::person(['country' => 'US']); //same as CRM::person()->search(...)
```

#### Special cases: restricted endpoints

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

##### Meta-data shortcuts

As those endpoints are mostly lists, you can also access that data through the cacheable `CRM::fieldList()` method,
which returns the information in a more organized fashion:
```php
$types = CRM::fieldList('contactType'); //singular!
print_r($types);
// yields an array of names, indexed by ID:
// (
//     [123] => Potential Customer
//     [124] => Current Customer
//     [125] => Uncategorized
//     [126] => Former Customer
// )

echo CRM::fieldList('contactType', 524131); search argument
// yields "Potential Customer". That argument searches on both sides of the array

$actTypes = CRM::fieldList('activityType', null, true); asks for "detailed response"
print_r($actTypes);
// yields the entire resources, still indexed by ID
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

#### Related Items
TODO
```php
$tasks = CRM::task()->related(10)->all();
$task_projects = CRM::task()->related(22)->projects();
$task_project = CRM::task()->related(22)->create(10, 'project');
$task_project = CRM::task()->related(22)->delete(27, 'project');
```

It's also possible to run batch operations, using Guzzle's concurrency features to speed up with parallel calls:
```php
TODO
```

To save on object creation you can store most of the half-way results:
```php
TODO
```

#### SubResources
TODO

#### Response objects
TODO

### Webhooks
TODO

[issue-7]: https://github.com/smith-carson/prosperworks-sdk/issues/7