<?php

namespace WPWhales;


use WPWhales\Container\Container;

/**
 * Get the available container instance.
 *
 * @param string|null $make
 * @param array $parameters
 * @return mixed|\WPWhales\Application
 */
function app($make = null, array $parameters = [])
{


    if (is_null($make)) {
        return Container::getInstance();
    }

    return Container::getInstance()->make($make, $parameters);
}
function resource_path($path){
    return app()->resourcePath($path);
}