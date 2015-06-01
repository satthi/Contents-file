<?php
use Cake\Routing\Router;

Router::plugin('ContentsFile', function ($routes) {
    $routes->fallbacks('InflectedRoute');
});
