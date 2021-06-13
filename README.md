# lumen-gateway

This package is copied from [larabook](https://github.com/larabook/gateway) and made changes to compatible with Lumen .


## Installation

First, install the package via Composer:

``` bash
composer require vidavh/lumen-gateway
```
- register facade:
```php
class_alias(\Vidavh\Gateway\Gateway::class, 'Gateway');
or
$app->withFacades(true, [
'Vidavh\Gateway\Gateway' => 'Gateway',
]);
```

- register config:

```php
$app->configure('gateway');
```

- register service provider:

```php
$app->register(\Vidavh\Gateway\PaymentServiceProvider::class);
```


