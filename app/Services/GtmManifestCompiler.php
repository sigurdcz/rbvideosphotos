<?php

namespace App\Services;

class GtmManifestCompiler
{
    public function __construct(private readonly GtmEventCatalog $catalog = new GtmEventCatalog())
    {
    }

    public function compile(array $site, array $definitions): array
    {
        $measurementId = trim((string)($site['google_analytics_id'] ?? ''));
        $manifest = [];

        foreach ($definitions as $definition) {
            if (empty($definition['enabled'])) {
                continue;
            }

            $eventKey = (string)($definition['event_key'] ?? '');
            if ($eventKey === '') {
                continue;
            }

            if ($measurementId === '') {
                throw new \RuntimeException('Chybí GA4 Measurement ID. Vyplň "Google Analytics ID" (G-XXXXXXXXXX) v Nastavení — GA4 event tagy ho potřebují.');
            }

            $catalogRow = $this->catalog->find($eventKey) ?? [];
            $tagName = (string)($definition['tag_name'] ?? ($catalogRow['tag_name'] ?? $this->catalog->makeTagName($eventKey, $eventKey)));
            $triggerName = (string)($definition['trigger_name'] ?? ($catalogRow['trigger_name'] ?? $this->catalog->makeTriggerName($eventKey, $eventKey)));
            $description = (string)($definition['description'] ?? ($catalogRow['description'] ?? ''));
            $category = (string)($definition['category'] ?? ($catalogRow['category'] ?? ''));

            $triggerManagementKey = $this->catalog->managementKey($eventKey, 'trigger');
            $tagManagementKey = $this->catalog->managementKey($eventKey, 'tag');

            $trigger = [
                'name' => $triggerName,
                'type' => 'customEvent',
                'customEventFilter' => [
                    [
                        'type' => 'equals',
                        'parameter' => [
                            ['type' => 'template', 'key' => 'arg0', 'value' => '{{_event}}'],
                            ['type' => 'template', 'key' => 'arg1', 'value' => $eventKey],
                        ],
                    ],
                ],
                'filter' => [],
                'notes' => $this->buildNotes($eventKey, $category, $description, 'trigger', $triggerManagementKey),
            ];

            $tag = [
                'name' => $tagName,
                'type' => 'gaawe',
                'parameter' => [
                    ['type' => 'template', 'key' => 'measurementIdOverride', 'value' => $measurementId],
                    ['type' => 'template', 'key' => 'eventName', 'value' => $eventKey],
                ],
                'firingTriggerId' => [],
                'blockingTriggerId' => [],
                'paused' => false,
                'notes' => $this->buildNotes($eventKey, $category, $description, 'tag', $tagManagementKey),
            ];

            $manifest[] = [
                'event_key' => $eventKey,
                'tag' => $tag,
                'trigger' => $trigger,
                'category' => $category,
                'description' => $description,
                'measurement_id' => $measurementId,
                'management_keys' => [
                    'tag' => $tagManagementKey,
                    'trigger' => $triggerManagementKey,
                ],
            ];
        }

        return $manifest;
    }

    public function hashDefinition(array $definition): string
    {
        return hash('sha256', json_encode($this->sortRecursive($definition), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    public function buildNotes(string $eventKey, string $category, string $description, string $entityType, ?string $managementKey = null): string
    {
        $parts = [
            GtmEventCatalog::EVENT_PREFIX,
            'event=' . $eventKey,
            'entity=' . $entityType,
            'category=' . $category,
        ];

        if ($managementKey !== null && $managementKey !== '') {
            $parts[] = 'management_key=' . $managementKey;
        }

        if ($description !== '') {
            $parts[] = 'description=' . $description;
        }

        return implode(' | ', $parts);
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            return array_map([$this, 'sortRecursive'], $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        return $value;
    }
}
