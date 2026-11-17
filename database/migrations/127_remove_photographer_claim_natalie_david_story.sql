-- Rewrite the Natalie and David story: plainer wording and remove the claim that
-- the photographer worked at this wedding. Replaces earlier generated versions only.
UPDATE portfolio_entries
SET description = '<p><time datetime="2026-10-03">3. října 2026</time> · Sportovně střelecký areál Jívová</p>
<p>Střelecký areál není místo, kde by většina snoubenců čekala svůj obřad. Natálie a David si tu 3. října 2026 přesto řekli své ano a právě to podzimnímu dni dodalo osobitý ráz. Jmenuji se Tomáš Šincl, jsem svatební kameraman z Olomouce a jejich svatbu jsem natáčel.</p>

<h2>Kniha s prázdnou kapitolou</h2>
<p>Obřad začal řečí, která zaujala hned první větou. Řečník přirovnal život Natálie a Davida ke knize plné kapitol. Dočetli se prý k té nové, v níž zatím nejsou žádná písmenka, protože ta napíšou teprve oni dva. Popřál jim, aby byla „sakra dlouhá“ a aby dostáli svému příjmení Zdařilovi. Když potom oba potvrdili, že do manželství vstupují dobrovolně, hosté odpověděli potleskem.</p>
<p>Obřad ve filmu nekrátím. Stačí vypustit jednu větu a pointa je pryč, a tady na ní stálo všechno. Hudbu pod takové chvíle nedávám, mluvené slovo si ji nepotřebuje.</p>

<h2>„Život není symetrický“</h2>
<p>Druhý řečník vsadil na nadsázku. Lásku prý nelze spočítat v Excelu, jinak by nám vyšlo, že „devět z deseti amerických vědců doporučuje manželství“. Jeho rada byla prostá: rozhodujte se srdcem.</p>
<p>Při proslovech hudbu stahuji. Hlas je to, co si novomanželé na videu za pár let ocení nejvíc, a hudba by z něj udělala kulisu. U vtipných řečí navíc rozhoduje načasování, které se snadno přehluší. Zvuk proto hlídám už při natáčení. Špatně nahranou řeč už ve střižně nezachráníte.</p>

<h2>Slova od nejbližších</h2>
<p>Rodina a přátelé přidali dojemná přání i vtipné vzpomínky na dětství Natálie a Davida.</p>
<p>Taková přání do filmu zařazuji vždy, protože se k nim novomanželé vracejí nejčastěji. Hosté v nich často řeknou víc než v oficiálním proslovu. Za třicet let budou mít větší váhu než dnes.</p>

<h2>Chcete podobné video na svou svatbu?</h2>
<p>Svatební video natáčím pod značkou RB Videos and photos. Jak takový film vzniká a proč se vyplatí, najdete v článku <a href="/proc-mit-svatebni-video">proč mít svatební video</a>. Pokud k videu chcete i fotografie, podívejte se na <a href="/svatebni-foto-video">svatební fotografie a video</a> nebo na stránku <a href="/svatebni-fotograf-olomouc">svatební fotograf Olomouc</a>.</p>
<p>Další svatby z Jívové jsou ve svatbě <a href="/svatba-jivova-lucie-miroslav">Lucie a Miroslava</a> a v <a href="/zarijova-svatba-jivova-simona-michal">zářijové svatbě Simony a Michala</a>. Ostatní naše práce najdete v sekci <a href="/nase-prace">Naše práce</a>, ceny v <a href="/cenik">ceníku</a> a odpovědi na časté otázky v <a href="/faq">častých dotazech</a>. Máte-li zájem o termín, <a href="/kontakt">napište mi</a>.</p>'
WHERE slug = 'svatba-jivova-natalie-david'
  AND description NOT LIKE '%Chcete podobné video na svou svatbu%'
  AND (description LIKE '%Obřad jako začátek nové kapitoly%'
       OR description LIKE '%Kniha s prázdnou kapitolou%');
