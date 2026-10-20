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
    $router->get('/admin/forgot-password', 'PasswordResetController', 'requestForm');
    $router->post('/admin/forgot-password', 'PasswordResetController', 'requestLink');
    $router->get('/admin/new-password', 'PasswordResetController', 'newPasswordForm');
    $router->get('/admin/reset-password', 'PasswordResetController', 'resetForm');
    $router->post('/admin/reset-password', 'PasswordResetController', 'resetPassword');
    $router->post('/admin/logout','AuthController', 'logout');

    $router->get('/admin/account/password', 'AccountController', 'passwordForm');
    $router->post('/admin/account/password', 'AccountController', 'changePassword');

    $router->get('/admin/messages', 'MessagesController', 'index');

    // Shared mailbox (team@ via IMAP/SMTP)
    $router->get('/admin/mail', 'MailController', 'index');
    $router->get('/admin/mail/thread', 'MailController', 'thread');
    $router->get('/admin/mail/compose', 'MailController', 'compose');
    $router->get('/admin/mail/drafts', 'MailController', 'drafts');
    $router->get('/admin/mail/poll', 'MailController', 'poll');
    $router->get('/admin/mail/attachment', 'MailController', 'attachment');
    $router->post('/admin/mail/send', 'MailController', 'send');
    $router->post('/admin/mail/draft/save', 'MailController', 'draftSave');
    $router->post('/admin/mail/draft/delete', 'MailController', 'draftDelete');
    $router->post('/admin/mail/attachment/upload', 'MailController', 'attachmentUpload');
    $router->post('/admin/mail/attachment/delete', 'MailController', 'attachmentDelete');
    $router->post('/admin/mail/sync', 'MailController', 'sync');
    $router->post('/admin/mail/thread/unread', 'MailController', 'markUnread');
    $router->get('/admin/mail/templates', 'MailController', 'templates');
    $router->post('/admin/mail/templates/save', 'MailController', 'templateSave');
    $router->post('/admin/mail/templates/delete', 'MailController', 'templateDelete');
    $router->get('/admin/analytics', 'VisitAnalyticsController', 'index');

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

    // Sablony webu
    $router->get('/admin/shablony',        'ThemeController', 'index');
    $router->post('/admin/shablony/activate', 'ThemeController', 'activate');
    $router->get('/admin/themes',          'ThemeController', 'index');
    $router->post('/admin/themes/activate', 'ThemeController', 'activate');

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
      $router->get('/admin/media/picker',  'MediaController', 'picker');
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

    // RFC 9116 security.txt
    $router->get('/admin/security-txt',         'SecurityTxtController', 'index');
    $router->post('/admin/security-txt/update', 'SecurityTxtController', 'update');

    // Google Tag Manager
    $router->get('/admin/gtm', 'GtmController', 'index');
    $router->get('/admin/gtm/connect', 'GtmOAuthController', 'connect');
    $router->get('/admin/gtm/oauth/callback', 'GtmOAuthController', 'callback');
    $router->post('/admin/gtm/disconnect', 'GtmOAuthController', 'disconnect');
    $router->post('/admin/gtm/connection/select', 'GtmOAuthController', 'selectConnection');
    $router->get('/admin/gtm/events', 'GtmController', 'events');
    $router->get('/admin/gtm/events/edit', 'GtmController', 'eventEdit');
    $router->post('/admin/gtm/events/update', 'GtmController', 'eventUpdate');
    $router->get('/admin/gtm/entities', 'GtmController', 'entities');
    $router->post('/admin/gtm/inventory/refresh', 'GtmController', 'inventoryRefresh');
    $router->post('/admin/gtm/plan', 'GtmController', 'plan');
    $router->post('/admin/gtm/apply', 'GtmController', 'apply');
    $router->post('/admin/gtm/entities/adopt', 'GtmController', 'adoptEntity');
    $router->post('/admin/gtm/entities/delete', 'GtmController', 'deleteEntity');
    $router->get('/admin/gtm/versions', 'GtmController', 'versions');
    $router->post('/admin/gtm/versions/create', 'GtmController', 'versionsCreate');
    $router->post('/admin/gtm/versions/publish', 'GtmController', 'versionsPublish');

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

    // Shared/global marketing sections
    $router->get('/admin/shared-sections', 'SharedSectionController', 'index');
    $router->get('/admin/shared-sections/edit', 'SharedSectionController', 'edit');
    $router->post('/admin/shared-sections/update', 'SharedSectionController', 'update');

    // Reviews
    $router->get('/admin/reviews',         'ReviewController', 'index');
    $router->get('/admin/reviews/create',  'ReviewController', 'create');
    $router->post('/admin/reviews/store',  'ReviewController', 'store');
    $router->get('/admin/reviews/edit',    'ReviewController', 'edit');
    $router->post('/admin/reviews/update', 'ReviewController', 'update');
    $router->post('/admin/reviews/delete', 'ReviewController', 'delete');

    // Setup / seed
    $router->get('/admin/setup',      'SetupController', 'index');
    $router->post('/admin/setup/run', 'SetupController', 'run');
    $router->post('/admin/setup/demo-content/start', 'SetupController', 'demoContentStart');
    $router->post('/admin/setup/demo-content/step', 'SetupController', 'demoContentStep');
    $router->get('/admin/setup/demo-content/status', 'SetupController', 'demoContentStatus');
    $router->post('/admin/setup/demo-content/retry', 'SetupController', 'demoContentRetry');
    $router->post('/admin/setup/armageddon', 'SetupController', 'armageddon');

    $router->dispatch();
} catch (\Throwable $e) {
    Runtime::handleException($e);
}
