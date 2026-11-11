-- New client portfolio story; append-only, safe to re-run without overwriting
-- later editorial changes. Reuse the portfolio player and final contact panel.
-- published_at stays NULL: the wedding date is known, the YouTube upload date
-- is not verified. PortfolioVideoSchema must not invent uploadDate or duration.
INSERT INTO portfolio_entries
    (site_id, title, slug, description, video_url, content_type, status,
     published_at, sort_order, meta_title, meta_description)
SELECT
    s.id,
    'Podzimní svatba Natálie a Davida v Jívové u Olomouce',
    'svatba-jivova-natalie-david',
    '<p><time datetime="2026-10-03">3. října 2026</time> · Sportovně střelecký areál Jívová</p>
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
<p>Pod značkou RB Videos and photos se věnuji videu, o fotografie se stará můj kolega Zdeněk Černohlávek. Pokud vás zajímá společná práce našeho týmu, najdete ji na stránce <a href="/svatebni-foto-video">svatební fotografie a video</a>. Pro další příběh ze stejné obce se můžete podívat na <a href="/svatba-jivova-lucie-miroslav">svatbu Lucie a Miroslava v Jívové</a>.</p>',
    'https://www.youtube.com/watch?v=E29Cz-B2UdA',
    'video',
    'published',
    NULL,
    0,
    'Svatba v Jívové u Olomouce – Natálie a David | Svatební video',
    'Podívejte se na svatební video Natálie a Davida ze Sportovně střeleckého areálu Jívová. Podzimní svatbu zachytil kameraman Tomáš Šincl.'
FROM sites s
WHERE s.id = (SELECT MIN(first_site.id) FROM sites first_site)
  AND NOT EXISTS (
      SELECT 1 FROM portfolio_entries p
      WHERE p.site_id = s.id AND p.slug = 'svatba-jivova-natalie-david'
  );

INSERT INTO portfolio_videos (portfolio_id, video_url, sort_order)
SELECT p.id, p.video_url, 0
FROM portfolio_entries p
WHERE p.slug = 'svatba-jivova-natalie-david'
  AND p.site_id = (SELECT MIN(s.id) FROM sites s)
  AND p.video_url = 'https://www.youtube.com/watch?v=E29Cz-B2UdA'
  AND NOT EXISTS (SELECT 1 FROM portfolio_videos v WHERE v.portfolio_id = p.id);
