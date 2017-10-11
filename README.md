# OrangeLogic Media Search API

Simple OrangeLogic Search API wrapper, in PHP.

Installation
------------

You can install orangelogic-api using Composer:

```
composer require odiemiranda/orangelogic-api
```

You will then need to:
* run ``composer install`` to get these dependencies added to your vendor directory
* add the autoloader to your application with this line: ``require("vendor/autoload.php")``

Alternatively you can just download the `OrangeLogic.php` file and include it manually:

```php
include('./OrangeLogic.php');
```

Examples
--------

Start by `use`-ing the class and creating an instance with domain, login, and password

```php
use \OdieMiranda\OrangeLogic\OrangeLogic;

$api = new OrangeLogic('domain.com', 'LoginName', 'password');
```

Then, search the media library 

```php
$api->search('Georgia: DR-TB patients find hope in new treatments');

print_r($api->getItems());
```

To get the search result next page

```php
$api->search('Georgia: DR-TB patients find hope in new treatments', '', '', 2);

print_r($api->getItems());
```

Contributing
------------

This is a fairly simple wrapper, but it will be made much better by contributions. If you like to suggest an improvement, please raise an issue to discuss it before make a pull-request.

Pull requests for bugs are more than welcome - please explain the bug you're trying to fix in the message.

