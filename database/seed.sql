-- SEO Mini CMS - Seed data
-- Default admin: admin@example.com / admin123

INSERT INTO `users` (`email`, `password_hash`, `role`) VALUES
('admin@example.com', '$2y$12$3XArAzstXOHYZDoCfwxw0.9ualmGywQg3jP2QMtMsIEaBThMJSTx2', 'admin');
-- Password: admin123
-- To generate your own: php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT, ['cost'=>12]);"

INSERT INTO `sites` (`name`, `domain`, `default_language`, `active_theme_key`) VALUES
('Můj web', 'https://example.cz', 'cs', NULL);

INSERT INTO `templates` (`site_id`, `name`, `html_source`) VALUES
(1, 'Testovací šablona', '<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test stránka</title>
</head>
<body>
  <header>
    <h1 data-block="hero_title">Svatební fotograf Olomouc</h1>
    <p data-block="hero_subtitle">Zachytím váš svatební den přirozeně a krásně.</p>
  </header>

  <main>
    <section>
      <h2 data-block="about_title">O mně</h2>
      <p data-block="about_text">Krátký text o fotografovi.</p>
    </section>

    <section>
      <h2 data-block="services_title">Služby</h2>
      <p data-block="services_text">Popis služeb.</p>
    </section>

    <section>
      <h2 data-block="contact_title">Kontakt</h2>
      <p data-block="contact_text">Kontaktujte mě.</p>
    </section>
  </main>
</body>
</html>');
