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

1. Get your API credentials.  
   > Currently, there's no "API User" on ProsperWorks, so you need an active user to communicate with the API.
   
   Sign in to ProsperWorks, go to **Settings > Preferences / API Keys**. There you will be able to generate a new API
   Key for the logged user. Copy that key, along with the user email.

2. Create a bootstrap file, or include the following code in the config section of your project: 
   `\ProsperWorks\Config::set($email, $token)`.
   
3. **[optional]** If you're going to use Webhooks to get updates from ProsperWorks, you'll also need to feed in three
   more arguments on that method:
   1. A webhook secret, that will be used to avoid unintended calls to your routes. That should be a plain string.
   2. A Root URL. That's probably the same domain/path you use for your systems, and what ProsperWorks will POST to.
      More information on the [Webhooks](#Webhooks) section.
   3. a Cryptography object. It should respond to `encryptBase64()` and `decryptBase64()`, both receiving and returning
      a string (it can also implement `\ProsperWorks\Interfaces\Crypt` to make things easier). It will be used to send
      an encrypted secret, and decrypt it to make sure the call you receive comes from ProsperWorks (or, at least,
      someone that has the encrypted secret).

4. **[optional]** To make some parts faster, you can also feed the sixth argument with a caching layer. It's mainly used
   to cache (for an hour) meta-data from the API, such as Custom Fields, Activity and Contact Types, and so on. That's
   information that rarely changes so it's safe to cache, making calls much faster (otherwise, for every resource with
   custom fields we would need to retrieve from the custom fields endpoint as well).
   
### Tip: "sandbox" account
After a while, when implementing this library for the first time, we spoke with a support representative about the lack
of a sandbox environment. They suggested us to create a trial account and use that instead of a user on the paying 
account, and mention to the Support that was being used to test-drive the API implementation - and thus, they would
extend the trial of that solo account for as long as it was needed.