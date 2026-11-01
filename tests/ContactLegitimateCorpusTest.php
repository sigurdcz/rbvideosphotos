<?php
declare(strict_types=1);

function register_contact_legitimate_corpus_tests(callable $test, callable $assert): void
{
    $test('all 1000 distinct synthetic legitimate inquiries pass default and stored spam profiles', static function () use ($assert): void {
        $rows = json_decode(file_get_contents(__DIR__ . '/fixtures/contact-legitimate-1000.json'), true, 32, JSON_THROW_ON_ERROR);
        $assert(count($rows) === 1000 && count(array_unique(array_column($rows, 'message'))) === 1000, 'Corpus must contain exactly 1,000 different inquiry bodies.');
        $categories = array_count_values(array_column($rows, 'category'));
        $assert(count($categories) === 20 && array_unique(array_values($categories)) === [50], 'Twenty distinct categories must each have fifty concrete messages.');
        $defaults = App\Services\ContactSpamConfig::defaults();
        foreach (['defaults' => null, 'stored' => App\Services\ContactSpamConfig::fromStored(App\Services\ContactSpamConfig::encode($defaults))] as $mode => $config) {
            $filter = new App\Services\ContactSpamFilter($config);
            $misses = [];
            foreach ($rows as $row) {
                $result = $filter->classify($row['name'], $row['contact'], $row['message']);
                if ($result['isSpam']) $misses[] = $row['id'] . '/' . $row['category'] . ':score=' . $result['score'] . ':' . implode(',', $result['reasons']);
            }
            $assert($misses === [], "$mode false positives " . count($misses) . '/1000: ' . implode('; ', array_slice($misses, 0, 12)));
        }
    });
}
