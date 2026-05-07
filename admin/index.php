<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Auth;
use App\Core\Env;
use App\Core\Router;
use App\Core\Runtime;

Env::load(BASE_PATH . '/.env');

header('Content-Type: text/html; charset=UTF-8');

// Init session
Auth::start();

// Timezone
date_default_timezone_set(\App\Core\Config::get('app', 'timezone', 'Europe/Prague'));
Runtime::boot('admin');
Runtime::startRequest();

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

    // Layouts (primary routes)
    $router->get('/admin/layouts',         'TemplateController', 'index');
    $router->get('/admin/layouts/create',  'TemplateController', 'create');
    $router->post('/admin/layouts/store',  'TemplateController', 'store');
    $router->get('/admin/layouts/edit',    'TemplateController', 'edit');
    $router->post('/admin/layouts/update', 'TemplateController', 'update');
    $router->post('/admin/layouts/delete', 'TemplateController', 'delete');
    $router->get('/admin/layouts/parse',   'TemplateController', 'parse');

    // Templates (legacy aliases)
    $router->get('/admin/templates',         'TemplateController', 'index');
    $router->get('/admin/templates/create',  'TemplateController', 'create');
    $router->post('/admin/templates/store',  'TemplateController', 'store');
    $router->get('/admin/templates/edit',    'TemplateController', 'edit');
    $router->post('/admin/templates/update', 'TemplateController', 'update');
    $router->post('/admin/templates/delete', 'TemplateController', 'delete');
    $router->get('/admin/templates/parse',   'TemplateController', 'parse');

    // Blocks
    $router->post('/admin/blocks/sync-template', 'BlockController', 'syncFromTemplate');

    // Inline editor (front-end, requires auth + CSRF)
    $router->post('/admin/inline/update', 'InlineController', 'update');
    $router->post('/admin/inline/upload', 'InlineController', 'upload');

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

    // Blog articles
    $router->get('/admin/blog',          'BlogController', 'index');
    $router->get('/admin/blog/create',   'BlogController', 'create');
    $router->post('/admin/blog/store',   'BlogController', 'store');
    $router->get('/admin/blog/edit',     'BlogController', 'edit');
    $router->post('/admin/blog/update',  'BlogController', 'update');
    $router->post('/admin/blog/delete',  'BlogController', 'delete');

    // Portfolio (Naše práce)
    $router->get('/admin/portfolio',         'PortfolioController', 'index');
    $router->get('/admin/portfolio/create',  'PortfolioController', 'create');
    $router->post('/admin/portfolio/store',  'PortfolioController', 'store');
    $router->get('/admin/portfolio/edit',    'PortfolioController', 'edit');
    $router->post('/admin/portfolio/update', 'PortfolioController', 'update');
    $router->post('/admin/portfolio/delete', 'PortfolioController', 'delete');

    // Setup / seed
    $router->get('/admin/setup',      'SetupController', 'index');
    $router->post('/admin/setup/run', 'SetupController', 'run');

    $router->dispatch();
} catch (\Throwable $e) {
    Runtime::handleException($e);
}
