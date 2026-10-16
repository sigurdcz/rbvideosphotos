UPDATE `content_blocks` b
JOIN `pages` p ON p.id = b.page_id
SET b.content = CONCAT(
  b.content,
  CASE p.url_path
    WHEN '/svatebni-foto-video' THEN '<h2>Výstupy, které dávají smysl i po letech</h2><p>Výběr záběrů nestavíme jen na efektních portrétech. Hlídáme také rodinu, přátele, místo, výzdobu a průběh dne, aby galerie i film fungovaly jako úplná vzpomínka. Fotografie odevzdáváme v kvalitě pro tisk i běžné sdílení. Před předáním vše kontrolujeme na různých obrazovkách a hotové soubory bezpečně zálohujeme po dobu uvedenou u zvolené služby. Máte-li zvláštní přání, řekněte nám je při konzultaci.</p>'
    WHEN '/rodinne-a-portretni-foceni' THEN '<h2>Fotografie připravené pro rodinu i tisk</h2><p>Při výběru hlídáme pestrost celé série: společné záběry, jednotlivé portréty, detaily i spontánní chvíle. Každou odevzdanou fotografii upravíme barevně a zkontrolujeme pleťové tóny bez nepřirozeného vyhlazování. Galerii otevřete v telefonu i počítači a můžete ji sdílet s rodinou. Pokud chcete fotografie tisknout, doporučíme vhodný poměr stran a pomůžeme vybrat snímky, které budou dobře fungovat v rámu nebo albu.</p>'
    WHEN '/reklamni-foto-video' THEN '<h2>Licence, předání a další použití</h2><p>Už při nabídce si vyjasníme rozsah použití, požadované formáty a případné varianty ořezu. Hotové soubory pojmenujeme a předáme přehledně, aby je mohl snadno převzít správce webu, grafik nebo marketingový tým. U opakovaných kampaní lze navázat na stejný obrazový styl a průběžně doplňovat nové fotografie či video. Citlivé materiály a neveřejné informace řešíme individuálně a před zveřejněním lze domluvit schvalovací náhled.</p>'
    ELSE ''
  END
)
WHERE b.block_key = 'body'
  AND p.url_path IN (
    '/svatebni-foto-video',
    '/rodinne-a-portretni-foceni',
    '/reklamni-foto-video'
  );
