<?php

namespace App\Core;

trait SlugTrait
{
    private static array $diacriticsMap = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n',
        'ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'a','Č'=>'c','Ď'=>'d','É'=>'e','Ě'=>'e','Í'=>'i','Ň'=>'n',
        'Ó'=>'o','Ř'=>'r','Š'=>'s','Ť'=>'t','Ú'=>'u','Ů'=>'u','Ý'=>'y','Ž'=>'z',
    ];

    /**
     * @param bool $allowSlash Povolí lomítko v slug (pro vnořené stránky).
     */
    private function makeSlug(string $value, string $fallback = '', bool $allowSlash = false): string
    {
        $src = trim($value) !== '' ? $value : $fallback;
        $s   = mb_strtolower(strtr($src, self::$diacriticsMap));

        if ($allowSlash) {
            $s = preg_replace('/[^a-z0-9\-\/]/', '-', $s);
            $s = preg_replace('/-+/', '-', $s);
            return trim($s, '-/');
        }

        $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
        $s = preg_replace('/[\s-]+/', '-', trim($s));
        return $s;
    }
}
