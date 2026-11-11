-- Rewrite the Natalie and David story with the cameraman own first-person
-- reasoning in every section (why scenes are kept, why music is muted).
-- Replaces only earlier generated versions; later editorial edits are kept.
UPDATE portfolio_entries
SET description = '<p><time datetime="2026-10-03">3. října 2026</time> · Sportovně střelecký areál Jívová</p>
<p>Střelecký areál nepatří mezi místa, kde by většina snoubenců čekala svůj obřad. Natálie a David si tu přesto 3. října 2026 řekli své ano a právě to dalo podzimnímu dni nezaměnitelný ráz. Jmenuji se Tomáš Šincl, jsem svatební kameraman z Olomouce a jejich den jsem měl možnost natáčet. Neobvyklé místo beru jako dar: film z něj se nedá splést s žádnou jinou svatbou.</p>

<h2>Kniha s prázdnou kapitolou</h2>
<p>Obřad začal řečí, která si hosty získala hned první větou. Řečník přirovnal život Natálie a Davida ke knize plné kapitol. Dočetli se prý k té nové, v níž zatím nejsou žádná písmenka, protože ta napíšou teprve oni dva. Popřál jim, aby byla „sakra dlouhá“ a aby dostáli svému příjmení Zdařilovi.</p>
<p>Když pak oba potvrdili, že do manželství vstupují dobrovolně, hosté odpověděli potleskem. Tuhle část filmu nikdy nekrátím a nepřekrývám hudbou. Obřad je páteř svatebního dne a kdyby se z řeči vytratila první věta, ztratí se i pointa. Takové okamžiky se nedají zopakovat, a proto jim dávám stejný prostor jako samotnému obrazu.</p>

<h2>„Život není symetrický“</h2>
<p>Druhý řečník vsadil na nadsázku. Lásku prý nelze spočítat v Excelu, jinak by nám vyšlo, že „devět z deseti amerických vědců doporučuje manželství“. Jeho rada byla přitom prostá: rozhodujte se srdcem.</p>
<p>Při proslovech hudbu vždy ztlumím, nebo ji úplně stáhnu. Hudba z řeči snadno udělá kulisu, a přitom je právě hlas to, co se svatebním filmem za pár let pozná nejdřív. Humor navíc stojí na rytmu a načasování a ten nechci přehlušit. Zvuk proto hlídám už při natáčení, ne až ve střižně, kde se špatně zaznamenaná řeč dopravit nedá.</p>

<h2>Slova od nejbližších</h2>
<p>Svatbu tvoří i lidé, kteří ji s párem prožili. Rodina a přátelé přidali dojemná přání i vtipné vzpomínky na dětství Natálie a Davida. Právě takové vzkazy mohou mít za třicet let ještě větší váhu než dnes.</p>
<p>Když film stavím, vzkazy od hostů zařazuji záměrně do druhé poloviny. Novomanželé se k nim vracejí nejčastěji a hosté v nich často řeknou víc než v hlavním proslovu. Obřad, řeči a přání proto nechápu jako tři kapitoly, ale jako jeden příběh o tom, co si lidé při této svatbě řekli.</p>

<h2>Svatební video a fotografie od jednoho týmu</h2>
<p>Pod značkou RB Videos and photos se věnuji videu, o fotografie se stará můj kolega Zdeněk Černohlávek. K jednomu týmu se držím záměrně: kameraman s fotografem si na svatbě nepřekážejí, domluví se předem a výsledné video a fotky k sobě vizuálně ladí. Společnou práci shrnuje stránka <a href="/svatebni-foto-video">svatební fotografie a video</a>, fotografické stránce se věnuje <a href="/svatebni-fotograf-olomouc">svatební fotograf Olomouc</a>. Pokud váháte, zda video na svatbu chcete, přečtěte si, <a href="/proc-mit-svatebni-video">proč mít svatební video</a>.</p>
<p>Další příběhy z Jívové najdete u <a href="/svatba-jivova-lucie-miroslav">svatby Lucie a Miroslava</a> a u <a href="/zarijova-svatba-jivova-simona-michal">zářijové svatby Simony a Michala</a>. Ostatní naše realizace jsou v sekci <a href="/nase-prace">Naše práce</a>, informace o cenách v <a href="/cenik">ceníku</a> a odpovědi na nejčastější otázky v <a href="/faq">častých dotazech</a>. Máte-li zájem o podobné video, <a href="/kontakt">napište nám</a>.'
WHERE slug = 'svatba-jivova-natalie-david'
  AND description NOT LIKE '%hudbu vždy ztlumím%'
  AND (description LIKE '%Obřad jako začátek nové kapitoly%'
       OR description LIKE '%Kniha s prázdnou kapitolou%');
