-- Use the cameraman own wording of the day as the backbone of the story and keep
-- the speeches and internal links. Replaces earlier generated versions only.
UPDATE portfolio_entries
SET description = '<p><time datetime="2026-10-03">3. října 2026</time> · Sportovně střelecký areál Jívová u Olomouce</p>
<p>Některé svatební dny mají zkrátka své kouzlo. A přesně takový byl i svatební den Natálie a Davida, který jsem měl možnost zachytit 3. října 2026 ve Sportovně střeleckém areálu v Jívové nedaleko Olomouce. Jmenuji se Tomáš Šincl a jako svatební kameraman z Olomouce jsem u toho nemohl chybět.</p>
<p>Krásný podzimní den, slunečné počasí a příjemná atmosféra vytvořily dokonalé kulisy pro jejich společný začátek. Všechno do sebe přirozeně zapadalo a celý den plynul přesně tak, jak měl.</p>
<p>Od prvních okamžiků bylo cítit, že si Natálie a David svůj den opravdu užívají. Nechyběly úsměvy, emoce, radost ani dojemné okamžiky, které dělají svatbu nezapomenutelnou.</p>

<h2>Proslovy, na které se nezapomíná</h2>
<p>Velkou roli sehráli také svědci, kteří byli naprosto sehraní. Jejich dechberoucí proslov patřil k okamžikům, na které se jen tak nezapomíná. Právě takové chvíle dávají svatebnímu dni osobitý charakter a stojí za to je uchovat nejen ve vzpomínkách, ale také ve svatebním videu.</p>
<p>Utkvěly mi dvě řeči. První přirovnala život Natálie a Davida ke knize plné kapitol: dočetli se prý k té nové, v níž zatím nejsou žádná písmenka, protože ta napíšou teprve oni dva. Řečník jim popřál, aby byla „sakra dlouhá“ a aby dostáli svému příjmení Zdařilovi. Druhá řeč připomněla, že lásku nelze spočítat v Excelu, jinak by nám vyšlo, že „devět z deseti amerických vědců doporučuje manželství“. Rada zněla jednoduše: rozhodujte se srdcem.</p>
<p>Při takových chvílích hudbu stahuji. Hlas je to, co si novomanželé ocení za pár let nejvíc, a hudba by z něj udělala kulisu. Zvuk proto hlídám už při natáčení, špatně nahranou řeč už ve střižně nezachráníte.</p>

<h2>Jívová: místo, kam se rád vracím</h2>
<p>Sportovně střelecký areál v Jívové jsem nenavštívil poprvé. Natáčel jsem zde už několik svateb a pokaždé mě toto místo dokázalo něčím příjemně překvapit. Areál slouží přímo svatbám a dalším společenským akcím.</p>
<p>Každá svatba je jiná, každý pár má svůj vlastní příběh a každá oslava přináší jedinečné okamžiky. Právě to mě na svatebním natáčení baví nejvíce.</p>
<p>Natálie a Davide, děkuji vám za důvěru a možnost být součástí vašeho krásného dne. Přeji vám, ať je vaše společná cesta plná lásky, radosti a stejně krásných okamžiků, jako byl právě tento říjnový den.</p>
<p><em>Děkuji, že jsem mohl váš příběh zachytit na film.</em></p>

<h2>Chcete podobné video na svou svatbu?</h2>
<p>Svatební video natáčím pod značkou RB Videos and photos. Proč se vyplatí, najdete v článku <a href="/proc-mit-svatebni-video">proč mít svatební video</a>. Pokud k videu chcete i fotografie, podívejte se na <a href="/svatebni-foto-video">svatební fotografie a video</a> nebo na stránku <a href="/svatebni-fotograf-olomouc">svatební fotograf Olomouc</a>.</p>
<p>Další svatby z Jívové jsou ve svatbě <a href="/svatba-jivova-lucie-miroslav">Lucie a Miroslava</a> a v <a href="/zarijova-svatba-jivova-simona-michal">zářijové svatbě Simony a Michala</a>. Ostatní naše práce najdete v sekci <a href="/nase-prace">Naše práce</a>, ceny v <a href="/cenik">ceníku</a> a odpovědi na časté otázky v <a href="/faq">častých dotazech</a>. Máte-li zájem o termín, <a href="/kontakt">napište mi</a>.</p>'
WHERE slug = 'svatba-jivova-natalie-david'
  AND description NOT LIKE '%kam se rád vracím%'
  AND (description LIKE '%Obřad jako začátek nové kapitoly%'
       OR description LIKE '%Kniha s prázdnou kapitolou%');
