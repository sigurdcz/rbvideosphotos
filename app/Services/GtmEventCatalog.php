<?php

namespace App\Services;

class GtmEventCatalog
{
    public const EVENT_PREFIX = '[SEO Editor]';

    public function all(): array
    {
        return [
            $this->event('lead_form_submit', 'Odeslani kontaktniho formulare', 'hlavni konverze', 'Uspesne odeslani kontaktniho formulare'),
            $this->event('phone_click', 'Kliknuti na telefonni cislo', 'mikro-konverze', 'Kliknuti na telefonni cislo'),
            $this->event('email_click', 'Kliknuti na e-mail', 'mikro-konverze', 'Kliknuti na e-mail'),
            $this->event('pricing_click', 'Kliknuti na cenik, balicek nebo cenu', 'mikro-konverze', 'Kliknuti na cenik, balicek nebo cenu'),
            $this->event('cta_click', 'Kliknuti na hlavni CTA tlacitko', 'mikro-konverze', 'Kliknuti na hlavni CTA tlacitko'),
            $this->event('gallery_open', 'Otevreni galerie nebo detailu fotky', 'engagement', 'Otevreni galerie nebo detailu fotky'),
            $this->event('gallery_image_view', 'Zobrazeni konkretni fotky v galerii', 'engagement', 'Zobrazeni konkretni fotky v galerii'),
            $this->event('gallery_category_click', 'Kliknuti na kategorii galerie', 'engagement', 'Kliknuti na kategorii galerie'),
            $this->event('video_play', 'Spusteni prezentacniho videa', 'engagement', 'Spusteni prezentacniho videa'),
            $this->event('scroll_90', 'Doscrollovani na 90 % stranky', 'engagement', 'Doscrollovani na 90 % stranky'),
            $this->event('social_click', 'Kliknuti na socialni sit', 'odchozi interakce', 'Kliknuti na socialni sit'),
            $this->event('external_link_click', 'Kliknuti na externi odkaz mimo web', 'odchozi interakce', 'Kliknuti na externi odkaz mimo web'),
            $this->event('file_download', 'Stazeni PDF, brozury nebo nabidky', 'mikro-konverze', 'Stazeni souboru'),
            $this->event('thank_you_page_view', 'Zobrazeni dekovaci stranky', 'kontrolni konverzni event', 'Zobrazeni dekovaci stranky'),
            $this->event('contact_section_view', 'Zobrazeni kontaktni sekce', 'engagement', 'Zobrazeni kontaktni sekce'),
            $this->event('service_detail_view', 'Zobrazeni detailu sluzby', 'engagement', 'Zobrazeni detailu sluzby'),
            $this->event('package_select', 'Vyber konkretniho balicku', 'silna mikro-konverze', 'Vyber balicku'),
            $this->event('booking_start', 'Zacatek rezervace terminu', 'silna mikro-konverze', 'Zacatek rezervace'),
            $this->event('booking_complete', 'Dokonceni rezervace terminu', 'hlavni konverze', 'Dokonceni rezervace'),
            $this->event('map_click', 'Kliknuti na mapu nebo navigaci', 'mikro-konverze', 'Kliknuti na mapu nebo navigaci'),
        ];
    }

    public function find(string $eventKey): ?array
    {
        foreach ($this->all() as $event) {
            if ($event['event_key'] === $eventKey) {
                return $event;
            }
        }

        return null;
    }

    public function defaultEnabled(): int
    {
        return 1;
    }

    public function makeTagName(string $eventKey, string $label): string
    {
        return self::EVENT_PREFIX . ' - ' . $label . ' (' . $eventKey . ')';
    }

    public function makeTriggerName(string $eventKey, string $label): string
    {
        return self::EVENT_PREFIX . ' Trigger - ' . $label . ' (' . $eventKey . ')';
    }

    public function managementKey(string $eventKey, string $entityType): string
    {
        return 'seo-editor:event:' . $eventKey . ':' . $entityType;
    }

    private function event(string $eventKey, string $label, string $category, string $description): array
    {
        return [
            'event_key' => $eventKey,
            'label' => $label,
            'description' => $description,
            'category' => $category,
            'tag_type' => 'gaawe',
            'trigger_type' => 'custom_event',
            'tag_name' => $this->makeTagName($eventKey, $label),
            'trigger_name' => $this->makeTriggerName($eventKey, $label),
            'tag_parameter_json' => null,
            'trigger_filter_json' => null,
            'notes_json' => null,
            'enabled' => $this->defaultEnabled(),
        ];
    }
}
