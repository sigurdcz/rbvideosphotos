<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Services\Render\ContactNormalizer;

/**
 * Pure data provider for the `kontakt` archetype (app/Views/archetypes/kontakt.phtml).
 *
 * Phase 4c of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServeKontaktPage(). Returns scalars/arrays only — no HTML
 * except the trusted, hardcoded social icon SVG (same trust level as
 * FooterViewData from phase 3). Never generates the CSRF token itself — it is
 * generated once by the caller (Auth::csrfToken()) and only carried through here,
 * per the security contract in TEMPLATE_REFACTOR_PLAN.md section 6.
 */
final class KontaktPageData
{
    /**
     * @return array{
     *   contactCards: list<array{type:string, phones?:list<array{href:string,display:string,inlineKey:string}>, email?:string, emailInlineKey?:string, addresses?:list<array{text:string,inlineKey:string}>}>,
     *   mapsUrl: string,
     *   heroActions: array{tel:string, email:string},
     *   faqItems: list<array{question:string,answer:string}>,
     *   form: array{csrfToken:string, honeypotField:string, sentState:?string}
     * }
     */
    public static function forSite(int $siteId, string $csrfToken, ?string $sentState): array
    {
        $contact = ContactNormalizer::enrichContactWithLegacyBlocks($siteId, (new ContactRepository())->findBySite($siteId) ?: []);

        $contactCards = [];

        $phones = [];
        foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
            $phone = trim((string)($contact[$phoneKey] ?? ''));
            if ($phone === '') {
                continue;
            }
            $phones[] = [
                'href' => 'tel:' . preg_replace('/\s+/', '', $phone),
                'display' => $phone,
                'inlineKey' => 'contact:' . $phoneKey,
            ];
        }
        if ($phones !== []) {
            $contactCards[] = ['type' => 'phone', 'phones' => $phones];
        }

        $email = trim((string)($contact['email'] ?? ''));
        if ($email !== '') {
            $contactCards[] = ['type' => 'email', 'email' => $email, 'emailInlineKey' => 'contact:email'];
        }

        $addresses = [];
        foreach (['address_1', 'address_2'] as $addressKey) {
            $address = trim((string)($contact[$addressKey] ?? ''));
            if ($address !== '') {
                $addresses[] = ['text' => $address, 'inlineKey' => 'contact:' . $addressKey];
            }
        }
        if ($addresses !== []) {
            $contactCards[] = ['type' => 'location', 'addresses' => $addresses];
        }

        $contactCards[] = ['type' => 'note'];

        $faqItems = [];
        try {
            foreach (array_slice((new FaqRepository())->allActive($siteId), 0, 4) as $faqItem) {
                $question = trim((string)($faqItem['question'] ?? ''));
                if ($question === '') {
                    continue;
                }
                $faqItems[] = [
                    'question' => $question,
                    'answer' => trim((string)($faqItem['answer'] ?? '')),
                ];
            }
        } catch (\Throwable $e) {
            $faqItems = [];
        }

        return [
            'contactCards' => $contactCards,
            'mapsUrl' => trim((string)($contact['maps_url'] ?? '')),
            'heroActions' => [
                'tel' => preg_replace('/\s+/', '', (string)($contact['phone_1'] ?? '')),
                'email' => trim((string)($contact['email'] ?? '')),
            ],
            'faqItems' => $faqItems,
            'form' => [
                'csrfToken' => $csrfToken,
                'honeypotField' => 'website',
                'sentState' => $sentState,
            ],
        ];
    }
}
