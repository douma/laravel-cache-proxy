<?php

namespace Douma\CacheProxy;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        CacheProxy::register();
    }
}
