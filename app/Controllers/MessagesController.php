<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

final class MessagesController extends Controller
{
    public function downloadNonSpam(array $params): void
    {
        $this->requireAdmin();
        $rows = (new \App\Repositories\MailArchiveRepository(Database::getInstance()))->nonSpamContacts();
        // Encode before emitting download headers: encoding/storage failures
        // must not be reported as a successful partial export.
        $json = json_encode([
            'exported_at' => gmdate('c'),
            'selection' => 'contact messages whose archived status is not spam; not independently verified as legitimate',
            'count' => count($rows),
            'messages' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        header('Cache-Control: private, no-store');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="nespamove-zpravy-' . gmdate('Y-m-d') . '.json"');
        echo $json;
    }

    public function index(array $params): void
    {
        $this->requireAdmin();
        header('Cache-Control: no-store');
        $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT);
        try {
            $data = (new \App\Repositories\MailArchiveRepository(Database::getInstance()))
                ->overview((string) ($_GET['status'] ?? ''), (string) ($_GET['kind'] ?? ''), $page === false ? 0 : $page);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo 'Neplatný filtr přehledu e-mailů.';
            return;
        }
        $this->view('messages.index', $data);
    }
}
