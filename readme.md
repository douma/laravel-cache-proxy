# Laravel Cache Proxy

## Installation 

`composer require douma/laravel-cache-proxy`

Register the following service provider:

```
Douma\CacheProxy\ServiceProvider::class
```

## Command–query separation

In order to use this library your methods must follow `Command Query Separation`, since
query methods are cached by the Proxy layer. 

## Example Illuminate\Http\Request

If you wish to cache the `Illuminate\Http\Request` object, add a `Cache`-prefix.
(Do not use this example in production!)

~~~php 
$request = new \Cache\Illuminate\Http\Request();
dd($request->get('a'));
~~~

## Example

If you have a `Repository`-object which needs to be cached:

```php
namespace App\Repositories;

class MyRepository 
{
    public function getData() : array 
    {
        sleep(3); //fake some heavy db call
        return [
            [
                'id'=>1,
                'title'=>'Some data',
                'content'=>'Some data content'
            ]       
        ];
    }
}

$repository = new App\Repositories\MyRepository();
```

You can simply prefix the namespace with `Cache`-prefix, to cache
all methods with a response:

```php
$repository = new Cache\App\Repositories\MyRepository();
```

### Proxy files

The library automatically creates a `Proxy`-file in the `cache`-folder:

```php
<?php namespace Cache\App\Repositories;

class MyRepository 
{
    private $subject;
    private $constructor = [];

    public function __construct()
    {
        $this->constructor = func_get_args();
    }

    private function call(string $name, array $arguments)
    {
        $hash = sha1(__CLASS__) . $name . print_r($arguments, true);
        if(cache()->has($hash)) {
            return unserialize(cache()->get($hash));
        }
        if(!$this->subject) {
            $this->subject = app()->make(\App\Repositories\MyRepository::class, $this->constructor);
        }
        $result = $this->subject->{$name}(...$arguments);
        if($result) {
            cache()->put($hash, serialize($result));
            return $result;
        }
    }

    public  function getData() : array	{ return $this->call("getData", func_get_args());	}
}
```

When the source file is changing the `Proxy` is automatically updated. 
