-- Add more internal links (verified live URLs) to the Natalie and David story.
-- Applies only to the narrative version that does not yet link to the price list.
UPDATE portfolio_entries
SET description = '<p><time datetime="2026-10-03">3. října 2026</time> · Sportovně střelecký areál Jívová</p>
<p>Střelecký areál nepatří mezi místa, kde by většina snoubenců čekala svůj obřad. Natálie a David si tu přesto 3. října 2026 řekli své ano, a právě to dalo celému podzimnímu dni nezaměnitelný ráz. Jmenuji se Tomáš Šincl, jsem svatební kameraman z Olomouce a jejich den jsem měl možnost natáčet.</p>

<h2>Kniha s prázdnou kapitolou</h2>
<p>Obřad začal řečí, která si hosty získala hned první větou. Řečník přirovnal život Natálie a Davida ke knize plné kapitol. Dočetli se prý k té nové, v níž zatím nejsou žádná písmenka, protože ta napíšou teprve oni dva. Popřál jim, aby byla „sakra dlouhá“ a aby dostáli svému příjmení Zdařilovi.</p>
<p>Když pak oba potvrdili, že do manželství vstupují dobrovolně, hosté odpověděli potleskem. Takový okamžik se nedá zopakovat, a proto mu patří stejná pozornost jako obrazu.</p>

<h2>„Život není symetrický“</h2>
<p>Druhý řečník vsadil na nadsázku. Lásku prý nelze spočítat v Excelu, jinak by nám vyšlo, že „devět z deseti amerických vědců doporučuje manželství“. Jeho rada byla přitom prostá: rozhodujte se srdcem.</p>
<p>Svatební proslov se nedá odehrát podruhé. Proto dbám na čistý zvuk, aby zůstal zachován hlas řečníka, jeho způsob vyprávění i reakce přítomných.</p>

<h2>Slova od nejbližších</h2>
<p>Svatbu tvoří i lidé, kteří ji s párem prožili. Rodina a přátelé přidali dojemná přání i vtipné vzpomínky na dětství Natálie a Davida. Právě takové vzkazy mohou mít za třicet let ještě větší váhu než dnes.</p>
<p>Obřad, proslovy a vzkazy hostů tak dohromady vydávají svědectví o jedné konkrétní svatbě v Jívové. Ne jen o tom, jak vypadala, ale hlavně o tom, co si při ní lidé řekli.</p>

<h2>Svatební video a fotografie od jednoho týmu</h2>
<p>Pod značkou RB Videos and photos se věnuji videu, o fotografie se stará můj kolega Zdeněk Černohlávek. Video a fotky tak vznikají u jednoho týmu a vizuálně k sobě ladí. Společnou práci shrnuje stránka <a href="/svatebni-foto-video">svatební fotografie a video</a>, fotografické stránce se věnuje <a href="/svatebni-fotograf-olomouc">svatební fotograf Olomouc</a>. Pokud váháte, zda video na svatbu chcete, přečtěte si, <a href="/proc-mit-svatebni-video">proč mít svatební video</a>.</p>
<p>Další příběhy z Jívové najdete u <a href="/svatba-jivova-lucie-miroslav">svatby Lucie a Miroslava</a> a u <a href="/zarijova-svatba-jivova-simona-michal">zářijové svatby Simony a Michala</a>. Ostatní naše realizace jsou v sekci <a href="/nase-prace">Naše práce</a>, informace o cenách v <a href="/cenik">ceníku</a> a odpovědi na nejčastější otázky v <a href="/faq">častých dotazech</a>. Máte-li zájem o podobné video, <a href="/kontakt">napište nám</a>.</p>'
WHERE slug = 'svatba-jivova-natalie-david'
  AND description LIKE '%Slova od nejbližších%'
  AND description NOT LIKE '%href="/cenik"%';
