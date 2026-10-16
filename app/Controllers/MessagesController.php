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
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * 50;
        $db = Database::getInstance();
        $total = (int) $db->query('SELECT COUNT(*) FROM mail_archive')->fetchColumn();
        $rows = $db->query('SELECT * FROM mail_archive ORDER BY id DESC LIMIT 50 OFFSET ' . $offset)->fetchAll();
        $this->view('messages.index', ['rows' => $rows, 'page' => $page, 'total' => $total]);
    }
}
