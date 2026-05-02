<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Auth;
use App\Core\Router;
use App\Core\Runtime;

// Init session
Auth::start();

// Timezone
date_default_timezone_set(\App\Core\Config::get('app', 'timezone', 'Europe/Prague'));
Runtime::boot('admin');
Runtime::startRequest();

// Error handling (dev)
if (\App\Core\Config::get('app', 'debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

try {
    $router = new Router();

    // Auth
    $router->get('/admin/login',  'AuthController', 'loginForm');
    $router->post('/admin/login', 'AuthController', 'login');
    $router->post('/admin/logout','AuthController', 'logout');

    // Dashboard
    $router->get('/admin', 'DashboardController', 'index');

    // Pages
    $router->get('/admin/pages',         'PageController', 'index');
    $router->get('/admin/pages/create',  'PageController', 'create');
    $router->post('/admin/pages/store',  'PageController', 'store');
    $router->get('/admin/pages/edit',    'PageController', 'edit');
    $router->post('/admin/pages/update', 'PageController', 'update');
    $router->post('/admin/pages/delete', 'PageController', 'delete');

    // Templates
    $router->get('/admin/templates',         'TemplateController', 'index');
    $router->get('/admin/templates/create',  'TemplateController', 'create');
    $router->post('/admin/templates/store',  'TemplateController', 'store');
    $router->get('/admin/templates/edit',    'TemplateController', 'edit');
    $router->post('/admin/templates/update', 'TemplateController', 'update');
    $router->post('/admin/templates/delete', 'TemplateController', 'delete');
    $router->get('/admin/templates/parse',   'TemplateController', 'parse');

    // Blocks
    $router->post('/admin/blocks/sync-template', 'BlockController', 'syncFromTemplate');

    // SEO
    $router->get('/admin/seo/edit',    'SeoController', 'edit');
    $router->post('/admin/seo/update', 'SeoController', 'update');

    // Media
    $router->get('/admin/media',         'MediaController', 'index');
    $router->post('/admin/media/upload', 'MediaController', 'upload');
    $router->post('/admin/media/update', 'MediaController', 'update');
    $router->post('/admin/media/delete', 'MediaController', 'delete');

    // Publish
    $router->get('/admin/publish',       'PublishController', 'index');
    $router->post('/admin/publish/page', 'PublishController', 'publishPage');
    $router->post('/admin/publish/site', 'PublishController', 'publishSite');

    // Redirects
    $router->get('/admin/redirects',        'RedirectController', 'index');
    $router->post('/admin/redirects/store', 'RedirectController', 'store');
    $router->post('/admin/redirects/delete','RedirectController', 'delete');

    // Settings
    $router->get('/admin/settings',        'SettingsController', 'index');
    $router->post('/admin/settings/update','SettingsController', 'update');

    $router->dispatch();
} catch (\Throwable $e) {
    Runtime::handleException($e);
}
