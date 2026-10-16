<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\SecurityHeaders;
use App\Services\InitialAdminService;

Env::load(__DIR__ . '/.env');
SecurityHeaders::send();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
Auth::start();
$_SESSION['initial_admin_csrf'] ??= bin2hex(random_bytes(32));
$message = '';
$created = false;
$status = 200;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrf = $_POST['csrf'] ?? '';
        if (!is_string($csrf) || !hash_equals($_SESSION['initial_admin_csrf'], $csrf)) {
            throw new RuntimeException('Platnost formuláře vypršela. Obnov stránku.');
        }
        foreach (['token', 'email', 'password'] as $field) {
            if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
                throw new RuntimeException('Vyplň všechna pole.');
            }
        }
        // Authenticate before opening the DB connection; never log submitted credentials.
        $expected = (string) Env::get('MIGRATE_TOKEN', '');
        if ($expected === '' || !hash_equals($expected, $_POST['token'])) {
            throw new RuntimeException('Neplatný MIGRATE_TOKEN.');
        }
        (new InitialAdminService(Database::getInstance(), $expected))->create(
            $_POST['token'], trim($_POST['email']), $_POST['password']
        );
        $_SESSION['initial_admin_csrf'] = bin2hex(random_bytes(32));
        $created = true;
        $message = 'První administrátor byl vytvořen. Přihlas se zvoleným e-mailem a heslem.';
        Logger::info('setup', 'Initial administrator created');
    } catch (PDOException $error) {
        $status = 500;
        $message = 'Databázová operace selhala. Ověř dokončení migrací a přístupová práva DB uživatele.';
        Logger::error('setup', 'Initial administrator database operation failed', ['code' => (string) $error->getCode()]);
    } catch (RuntimeException $error) {
        $status = 400;
        $message = $error->getMessage();
    } catch (Throwable $error) {
        $status = 500;
        $message = 'Vytvoření účtu selhalo. Zkontroluj, že migrace proběhly; podrobnosti jsou v serverovém logu.';
        Logger::error('setup', 'Initial administrator creation failed', ['type' => get_class($error)]);
    }
}
http_response_code($status);
function escapeInitialAdmin(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>První administrátor</title>
<style>body{font:16px system-ui;max-width:520px;margin:60px auto;padding:20px}label{display:block;margin:18px 0}input{display:block;box-sizing:border-box;width:100%;padding:10px;margin-top:6px}button{padding:12px 18px}p{line-height:1.5}</style></head><body>
<h1>První administrátor</h1>
<p>Pro novou instalaci po migracích. Formulář funguje pouze, dokud je tabulka uživatelů prázdná. Existující účty nepřepisuje.</p>
<?php if ($message !== ''): ?><p role="status"><?= escapeInitialAdmin($message) ?></p><?php endif; ?>
<?php if ($created): ?>
<p><a href="/admin/login">Přihlásit se do administrace</a></p>
<?php else: ?>
<form method="post" action="/bootstrap-admin.php">
<input type="hidden" name="csrf" value="<?= escapeInitialAdmin($_SESSION['initial_admin_csrf']) ?>">
<label>MIGRATE_TOKEN ze serverového .env<input type="password" name="token" required autocomplete="off"></label>
<label>E-mail administrátora<input type="email" name="email" value="admin@example.com" maxlength="255" required autocomplete="username"></label>
<label>Nové heslo (alespoň 12 znaků, nejvýše 72 bajtů)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
<button type="submit">Vytvořit prvního administrátora</button>
</form>
<?php endif; ?>
</body></html>
