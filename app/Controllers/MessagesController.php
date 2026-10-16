<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

final class MessagesController extends Controller
{
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
