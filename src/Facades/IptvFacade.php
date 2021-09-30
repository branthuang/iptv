<?php
namespace Dadaodata\Iptv\Facades;

use Illuminate\Support\Facades\Facade;

class IptvFacade extends facade
{
    protected static function getFacadeAccessor()
    {
        return 'iptv';
    }
}
