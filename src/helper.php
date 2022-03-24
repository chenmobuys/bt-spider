<?php

use BTSpider\Application;

if (!function_exists('btspider_base_path')) {

    function btspider_base_path($path = '')
    {
        return  Application::getInstance()->basePath($path);
    }
}
