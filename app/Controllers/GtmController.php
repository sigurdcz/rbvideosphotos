<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Repositories\GtmContainerVersionRepository;
use App\Repositories\GtmConnectionRepository;
use App\Repositories\GtmEventDefinitionRepository;
use App\Repositories\GtmManagedEntityRepository;
use App\Repositories\GtmSyncOperationRepository;
use App\Repositories\GtmSyncRunRepository;
use App\Repositories\SiteRepository;
use App\Services\GtmApiClient;
use App\Services\GtmApplyService;
use App\Services\GtmAuditService;
use App\Services\GtmConnectionService;
use App\Services\GtmEventCatalog;
use App\Services\GtmInventoryService;
use App\Services\GtmPlanService;
use App\Services\GtmVersionService;
use RuntimeException;

class GtmController extends Controller
{
    private GtmEventCatalog $catalog;
    private GtmConnectionService $connections;
    private GtmInventoryService $inventory;
    private GtmPlanService $planner;
    private GtmApplyService $applier;
    private GtmVersionService $versions;
    private GtmAuditService $audit;

    public function __construct()
    {
        $this->catalog = new GtmEventCatalog();
        $this->connections = new GtmConnectionService();
        $this->inventory = new GtmInventoryService();
        $this->planner = new GtmPlanService();
        $this->applier = new GtmApplyService();
        $this->versions = new GtmVersionService();
        $this->audit = new GtmAuditService();
    }

    public function index(array $params): void
    {
        $this->requireAdmin();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }
        $connection = $this->connections->loadForSite((int) $site['id']);
        $inventory = $this->safeInventory((int) $site['id']);
        $definitions = (new GtmEventDefinitionRepository())->allBySite((int) $site['id']);
        $entities = (new GtmManagedEntityRepository())->allBySite((int) $site['id']);
        $runs = (new GtmSyncRunRepository())->allBySite((int) $site['id'], 8);
        $versions = (new GtmContainerVersionRepository())->allBySite((int) $site['id'], 8);

        $this->view('gtm.index', [
            'site' => $site,
            'connection' => $connection,
            'inventory' => $inventory,
            'definitions' => $definitions,
            'entities' => $entities,
            'runs' => $runs,
            'versions' => $versions,
            'catalogCount' => count($this->catalog->all()),
            'configReady' => $this->connections->isConfigured(),
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function events(array $params): void
    {
        $this->requireAdmin();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }
        $definitions = (new GtmEventDefinitionRepository())->allBySite((int) $site['id']);

        $this->view('gtm.events', [
            'site' => $site,
            'definitions' => $definitions,
            'catalog' => $this->catalog->all(),
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function eventEdit(array $params): void
    {
        $this->requireAdmin();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }
        $id = (int) ($_GET['id'] ?? 0);
        $repo = new GtmEventDefinitionRepository();
        $definition = $id > 0 ? $repo->findById($id) : null;
        if (!$definition || (int) $definition['site_id'] !== (int) $site['id']) {
            $this->flash('error', 'GTM event nebyl nalezen.');
            $this->redirect('/admin/gtm/events');
        }

        $this->view('gtm.event_form', [
            'site' => $site,
            'definition' => $definition,
            'catalogItem' => $this->catalog->find((string) $definition['event_key']),
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function eventUpdate(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();

        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $repo = new GtmEventDefinitionRepository();
        $definition = $id > 0 ? $repo->findById($id) : null;
        if (!$definition || (int) $definition['site_id'] !== (int) $site['id']) {
            $this->flash('error', 'GTM event nebyl nalezen.');
            $this->redirect('/admin/gtm/events');
        }

        $label = trim((string) ($_POST['label'] ?? $definition['label']));
        $category = trim((string) ($_POST['category'] ?? $definition['category']));
        if ($label === '' || $category === '') {
            $this->flash('error', 'Nazev a kategorie jsou povinne.');
            $this->redirect('/admin/gtm/events/edit?id=' . $id);
        }

        try {
            $repo->update($id, [
                'label' => $label,
                'description' => trim((string) ($_POST['description'] ?? '')),
                'category' => $category,
                'enabled' => !empty($_POST['enabled']) ? 1 : 0,
                'trigger_type' => trim((string) ($_POST['trigger_type'] ?? $definition['trigger_type'])),
                'tag_type' => trim((string) ($_POST['tag_type'] ?? $definition['tag_type'])),
                'tag_name' => trim((string) ($_POST['tag_name'] ?? $definition['tag_name'])),
                'trigger_name' => trim((string) ($_POST['trigger_name'] ?? $definition['trigger_name'])),
                'tag_parameter_json' => $this->decodeTextareaJson($_POST['tag_parameter_json'] ?? ''),
                'trigger_filter_json' => $this->decodeTextareaJson($_POST['trigger_filter_json'] ?? ''),
                'notes_json' => $this->decodeTextareaJson($_POST['notes_json'] ?? ''),
            ]);
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/gtm/events/edit?id=' . $id);
        }

        $this->audit->info('gtm.event.update', 'GTM event definition updated', [
            'site_id' => $site['id'],
            'event_key' => $definition['event_key'],
            'event_definition_id' => $id,
        ]);

        $this->flash('success', 'GTM event ulozen.');
        $this->redirect('/admin/gtm/events/edit?id=' . $id);
    }

    public function entities(array $params): void
    {
        $this->requireAdmin();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }
        $connection = $this->connections->loadForSite((int) $site['id']);
        $inventory = $this->safeInventory((int) $site['id']);
        $entities = (new GtmManagedEntityRepository())->allBySite((int) $site['id']);
        $planRun = (int) ($_GET['run_id'] ?? 0);
        $run = $planRun > 0 ? (new GtmSyncRunRepository())->findById($planRun) : null;
        $operations = $run ? (new GtmSyncOperationRepository())->allByRun($planRun) : [];

        $this->view('gtm.entities', [
            'site' => $site,
            'connection' => $connection,
            'inventory' => $inventory,
            'entities' => $entities,
            'run' => $run,
            'operations' => $operations,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function inventoryRefresh(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        try {
            $inventory = $this->inventory->load((int) $site['id']);
            $this->audit->info('gtm.inventory.refresh', 'Inventory refreshed', [
                'site_id' => $site['id'],
                'tags' => count($inventory['tags'] ?? []),
                'triggers' => count($inventory['triggers'] ?? []),
                'variables' => count($inventory['variables'] ?? []),
            ]);
            $this->flash('success', 'Inventar GTM byl nacten.');
        } catch (RuntimeException $e) {
            $this->connections->updateFailure((int) $site['id'], $e->getMessage());
            $this->audit->error('gtm.inventory.refresh', 'Inventory refresh failed', [
                'site_id' => $site['id'],
                'error' => $e->getMessage(),
            ]);
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/admin/gtm');
    }

    public function plan(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }

        try {
            $inventory = $this->inventory->loadCached((int) $site['id']);
            $definitions = (new GtmEventDefinitionRepository())->allBySite((int) $site['id']);
            $user = Auth::user() ?? [];
            $plan = $this->planner->build($site, $inventory['connection'], $inventory, $definitions);
            $runId = (new GtmSyncRunRepository())->create([
                'site_id' => $site['id'],
                'connection_id' => $inventory['connection']['id'] ?? null,
                'user_id' => $user['id'] ?? null,
                'run_type' => 'plan',
                'status' => $plan['summary']['conflict'] > 0 ? 'conflict' : 'succeeded',
                'summary' => 'GTM plan',
                'sync_status_json' => $plan['summary'],
                'merge_conflicts_json' => $plan['conflicts'],
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            (new GtmSyncOperationRepository())->createMany($runId, $plan['operations']);
            $this->flash('success', 'Plan pripraven. Operaci: ' . $plan['summary']['total']);
            $this->redirect('/admin/gtm/entities?run_id=' . $runId);
        } catch (RuntimeException $e) {
            $this->connections->updateFailure((int) $site['id'], $e->getMessage());
            $this->audit->error('gtm.plan', 'Plan build failed', [
                'site_id' => $site['id'],
                'error' => $e->getMessage(),
            ]);
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/gtm/entities');
        }
    }

    public function apply(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }

        try {
            $connection = $this->connections->requireForSite((int) $site['id']);
            $inventory = $this->inventory->loadCached((int) $site['id']);
            $definitions = (new GtmEventDefinitionRepository())->allBySite((int) $site['id']);
            $user = Auth::user() ?? [];
            $plan = $this->planner->build($site, $connection, $inventory, $definitions);
            if ($plan['summary']['conflict'] > 0) {
                $this->flash('error', 'Plan obsahuje konflikty. Nejdřív je vyres.');
                $this->redirect('/admin/gtm/entities');
            }

            $result = $this->applier->apply((int) $site['id'], $site, $connection, $plan, (int) ($user['id'] ?? 0));
            $this->flash('success', 'Plan byl aplikovan. Run ID: ' . $result['run_id']);
            $this->redirect('/admin/gtm/entities?run_id=' . $result['run_id']);
        } catch (RuntimeException $e) {
            $this->connections->updateFailure((int) $site['id'], $e->getMessage());
            $this->audit->error('gtm.apply', 'Apply failed', [
                'site_id' => $site['id'],
                'error' => $e->getMessage(),
            ]);
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/gtm/entities');
        }
    }

    public function adoptEntity(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        $id = (int) ($_POST['entity_id'] ?? 0);
        $repo = new GtmManagedEntityRepository();
        $entity = $repo->findById($id);
        if (!$entity || (int) $entity['site_id'] !== (int) $site['id']) {
            $this->flash('error', 'Entity nenalezena.');
            $this->redirect('/admin/gtm/entities');
        }

        $repo->update($id, ['ownership' => 'adopted']);
        $this->audit->info('gtm.entity.adopt', 'Entity adopted', [
            'site_id' => $site['id'],
            'entity_id' => $id,
            'management_key' => $entity['management_key'],
        ]);
        $this->flash('success', 'Entity prevzata do spravy.');
        $this->redirect('/admin/gtm/entities');
    }

    public function deleteEntity(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        $id = (int) ($_POST['entity_id'] ?? 0);
        $repo = new GtmManagedEntityRepository();
        $entity = $repo->findById($id);
        if (!$entity || (int) $entity['site_id'] !== (int) $site['id']) {
            $this->flash('error', 'Entity nenalezena.');
            $this->redirect('/admin/gtm/entities');
        }

        if (!in_array($entity['ownership'], ['managed', 'adopted'], true)) {
            $this->flash('error', 'Externi entity nelze mazat bez prevzeti do spravy.');
            $this->redirect('/admin/gtm/entities');
        }

        try {
            if (!empty($entity['remote_path'])) {
                $connection = $this->connections->requireForSite((int) $site['id']);
                $token = trim((string) ($connection['access_token'] ?? ''));
                if ($token === '') {
                    $connection = $this->connections->refreshAccessToken((int) $site['id']);
                    $token = trim((string) ($connection['access_token'] ?? ''));
                }

                $api = new GtmApiClient();
                $this->deleteRemoteEntity($api, $token, (string) $entity['entity_type'], (string) $entity['remote_path'], $entity['fingerprint'] ?? null);
            }

            $repo->deleteById($id);
            $this->audit->info('gtm.entity.delete', 'Entity deleted', [
                'site_id' => $site['id'],
                'entity_id' => $id,
                'management_key' => $entity['management_key'],
            ]);
            $this->flash('success', 'Entity smazana.');
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/admin/gtm/entities');
    }

    public function versions(array $params): void
    {
        $this->requireAdmin();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        if (!$this->ensureDefinitions($site['id'])) {
            return;
        }
        $connection = $this->connections->loadForSite((int) $site['id']);
        $versions = (new GtmContainerVersionRepository())->allBySite((int) $site['id'], 16);

        $this->view('gtm.versions', [
            'site' => $site,
            'connection' => $connection,
            'versions' => $versions,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function versionsCreate(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        $name = trim((string) ($_POST['version_name'] ?? ''));
        $notes = trim((string) ($_POST['version_notes'] ?? ''));
        if ($name === '') {
            $this->flash('error', 'Nazev verze je povinny.');
            $this->redirect('/admin/gtm/versions');
        }

        try {
            $connection = $this->connections->requireForSite((int) $site['id']);
            $user = Auth::user() ?? [];
            $result = $this->versions->createVersion((int) $site['id'], $connection, (int) ($user['id'] ?? 0), $name, $notes);
            $this->flash('success', 'Verze GTM byla vytvorena.');
            $this->redirect('/admin/gtm/versions');
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/admin/gtm/versions');
        }
    }

    public function versionsPublish(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();
        $site = $this->siteOrFlash();
        if (!$site) {
            return;
        }

        $versionPath = trim((string) ($_POST['version_path'] ?? ''));
        if ($versionPath === '') {
            $this->flash('error', 'Vyber verzi pro publikaci.');
            $this->redirect('/admin/gtm/versions');
        }

        try {
            $connection = $this->connections->requireForSite((int) $site['id']);
            $user = Auth::user() ?? [];
            $this->versions->publishVersion((int) $site['id'], $connection, (int) ($user['id'] ?? 0), $versionPath);
            $this->flash('success', 'Verze GTM byla publikovana.');
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/admin/gtm/versions');
    }

    private function ensureDefinitions(int $siteId): bool
    {
        try {
            (new GtmEventDefinitionRepository())->ensureCatalog($siteId, $this->catalog->all());
            return true;
        } catch (\Throwable $e) {
            $this->flash('error', 'GTM databaze neni pripravena. Spust migrace 016_create_gtm_admin_tables.sql a 017_add_gtm_inventory_snapshot.sql.');
            $this->audit->error('gtm.catalog.ensure', 'GTM catalog setup failed', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            $this->redirect('/admin');
            return false;
        }
    }

    private function siteOrFlash(): ?array
    {
        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin');
        }

        return $site;
    }

    private function safeInventory(int $siteId): array
    {
        try {
            return $this->inventory->loadCached($siteId);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function decodeTextareaJson(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON pole obsahuje neplatna data.');
        }

        return $decoded;
    }

    private function deleteRemoteEntity(GtmApiClient $api, string $accessToken, string $type, string $remotePath, ?string $fingerprint): void
    {
        if ($type === 'tag') {
            $api->deleteTag($accessToken, $remotePath, $fingerprint);
            return;
        }
        if ($type === 'trigger') {
            $api->deleteTrigger($accessToken, $remotePath, $fingerprint);
            return;
        }
        if ($type === 'variable') {
            $api->deleteVariable($accessToken, $remotePath, $fingerprint);
            return;
        }

        throw new RuntimeException('Mazani tohoto typu entity neni podporovano.');
    }
}
