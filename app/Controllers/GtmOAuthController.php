<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Repositories\SiteRepository;
use App\Services\GtmApiClient;
use App\Services\GtmConnectionService;
use App\Services\GtmInventoryService;
use RuntimeException;

class GtmOAuthController extends Controller
{
    public function __construct(
        private readonly GtmConnectionService $connections = new GtmConnectionService(),
        private readonly GtmInventoryService $inventory = new GtmInventoryService(),
    ) {
    }

    public function connect(array $params): void
    {
        $this->requireAdmin();
        if (!$this->connections->isConfigured()) {
            $this->flash('error', 'GTM OAuth neni nakonfigurovan. Doplň GTM_CLIENT_ID, GTM_CLIENT_SECRET a GTM_ENCRYPTION_KEY.');
            $this->redirect('/admin/gtm');
        }

        $state = bin2hex(random_bytes(24));
        Auth::start();
        $_SESSION['gtm_oauth_state'] = $state;
        $_SESSION['gtm_oauth_started_at'] = time();
        $_SESSION['gtm_oauth_return_to'] = '/admin/gtm';

        $this->redirect($this->connections->authorizationUrl($state));
    }

    public function callback(array $params): void
    {
        $this->requireAdmin();
        Auth::start();

        $state = (string) ($_GET['state'] ?? '');
        $expected = (string) ($_SESSION['gtm_oauth_state'] ?? '');
        if ($state === '' || $expected === '' || !hash_equals($expected, $state)) {
            $this->flash('error', 'OAuth state nesouhlasi.');
            $this->redirect('/admin/gtm');
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            $this->flash('error', 'OAuth callback neobsahuje autorizační kod.');
            $this->redirect('/admin/gtm');
        }

        try {
            $tokenResponse = (new GtmApiClient())->exchangeCodeForToken($code);
            $this->connections->storeTokenResponse($this->siteId(), $tokenResponse);
            try {
                $this->inventory->load($this->siteId());
            } catch (RuntimeException $inventoryError) {
                unset($_SESSION['gtm_oauth_state'], $_SESSION['gtm_oauth_started_at']);
                $this->flash('warning', 'Google účet byl pripojen, ale inventar se nepodarilo nacist: ' . $inventoryError->getMessage());
                $this->redirect('/admin/gtm');
            }
            unset($_SESSION['gtm_oauth_state'], $_SESSION['gtm_oauth_started_at']);
            $this->flash('success', 'Google účet byl pripojen.');
            $this->redirect('/admin/gtm');
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/gtm');
        }
    }

    public function disconnect(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $this->connections->disconnect($this->siteId());
        $this->flash('success', 'Google účet byl odpojen.');
        $this->redirect('/admin/gtm');
    }

    public function selectConnection(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $siteId = $this->siteId();
        $selected = [
            'account_id' => trim((string) ($_POST['account_id'] ?? '')) ?: null,
            'container_id' => trim((string) ($_POST['container_id'] ?? '')) ?: null,
            'workspace_id' => trim((string) ($_POST['workspace_id'] ?? '')) ?: null,
        ];

        try {
            $inventory = $this->inventory->loadCached($siteId);
            $selection = $this->resolveSelection($inventory, $selected);
            $this->connections->updateSelection($siteId, $selection);
            $this->flash('success', 'GTM vyber byl ulozen.');
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/admin/gtm');
    }

    private function siteId(): int
    {
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            throw new RuntimeException('Web nenalezen.');
        }

        return (int) $site['id'];
    }

    private function resolveSelection(array $inventory, array $selected): array
    {
        $selection = [
            'account_id' => null,
            'account_name' => null,
            'container_id' => null,
            'container_name' => null,
            'workspace_id' => null,
            'workspace_name' => null,
            'workspace_path' => null,
            'workspace_fingerprint' => null,
        ];
        $account = $this->findById($inventory['accounts'] ?? [], $selected['account_id'] ?? null, 'accountId', ['name', 'accountName']);
        if ($account) {
            $selection['account_id'] = (string) ($account['accountId'] ?? $account['account_id'] ?? $selected['account_id']);
            $selection['account_name'] = (string) ($account['name'] ?? $account['accountName'] ?? $selection['account_id']);
        }

        $container = $this->findById($inventory['containers'] ?? [], $selected['container_id'] ?? null, 'containerId', ['name']);
        if ($container) {
            $selection['container_id'] = (string) ($container['containerId'] ?? $selected['container_id']);
            $selection['container_name'] = (string) ($container['name'] ?? $selection['container_id']);
        }

        $workspace = $this->findById($inventory['workspaces'] ?? [], $selected['workspace_id'] ?? null, 'workspaceId', ['name', 'path']);
        if ($workspace) {
            $selection['workspace_id'] = (string) ($workspace['workspaceId'] ?? $selected['workspace_id']);
            $selection['workspace_name'] = (string) ($workspace['name'] ?? $selection['workspace_id']);
            $selection['workspace_path'] = (string) ($workspace['path'] ?? '');
            $selection['workspace_fingerprint'] = (string) ($workspace['fingerprint'] ?? '');
        }

        return $selection;
    }

    private function findById(array $items, ?string $id, string $key, array $nameKeys): ?array
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        foreach ($items as $item) {
            $value = (string) ($item[$key] ?? '');
            if ($value === $id) {
                return $item;
            }
        }

        return null;
    }
}
