<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

$routes->scope('/contents_file', ['plugin' => 'ContentsFile'], function (RouteBuilder $routes) {
    $routes->fallbacks('InflectedRoute');
});