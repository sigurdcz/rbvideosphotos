<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

final class BootstrapTestStatement extends PDOStatement
{
    public mixed $result = null;
    public function __construct(private Closure $operation) {}
    public function execute(?array $params = null): bool { $this->result = ($this->operation)($params ?? []); return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->result; }
}
final class BootstrapTestDb extends PDO
{
    public array $users = [];
    public bool $lockAvailable = true;
    public int $released = 0;
    public int $calls = 0;
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->calls++;
        $statement = new BootstrapTestStatement(fn() => match ($query) {
            'SELECT DATABASE()' => 'bootstrap_test',
            'SELECT COUNT(*) FROM users' => count($this->users),
            default => throw new RuntimeException('Unexpected query'),
        });
        $statement->execute();
        return $statement;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->calls++;
        return new BootstrapTestStatement(function (array $params) use ($query) {
            if (str_starts_with($query, 'SELECT GET_LOCK')) { return $this->lockAvailable ? 1 : 0; }
            if (str_starts_with($query, 'SELECT RELEASE_LOCK')) { $this->released++; return 1; }
            if ($query === "INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'admin')") {
                $this->users[] = ['email' => $params[0], 'hash' => $params[1], 'role' => 'admin']; return 1;
            }
            throw new RuntimeException('Unexpected statement');
        });
    }
}
function checkBootstrap(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
function deniedBootstrap(Closure $operation): void {
    try { $operation(); } catch (RuntimeException $error) { return; }
    throw new RuntimeException('Expected rejection');
}
$db = new BootstrapTestDb();
$service = new App\Services\InitialAdminService($db, 'private-test-token');
deniedBootstrap(fn() => $service->create('wrong', 'owner@example.com', 'StrongPassword123!'));
checkBootstrap($db->calls === 0, 'invalid token rejected before database access');
deniedBootstrap(fn() => (new App\Services\InitialAdminService($db, ''))->create('', 'owner@example.com', 'StrongPassword123!'));
checkBootstrap($db->calls === 0, 'missing configured token fails closed');
deniedBootstrap(fn() => $service->create('private-test-token', 'owner@example.com', 'admin123'));
checkBootstrap($db->calls === 0, 'short password rejected before write');
$db->lockAvailable = false;
deniedBootstrap(fn() => $service->create('private-test-token', 'owner@example.com', 'StrongPassword123!'));
checkBootstrap($db->users === [], 'unavailable database lock prevents creation');
$db->lockAvailable = true;
$service->create('private-test-token', 'owner@example.com', 'StrongPassword123!');
checkBootstrap(count($db->users) === 1 && password_verify('StrongPassword123!', $db->users[0]['hash']) && $db->users[0]['role'] === 'admin', 'first account created with hashed password and admin role');
deniedBootstrap(fn() => $service->create('private-test-token', 'attacker@example.com', 'AnotherPassword123!'));
checkBootstrap(count($db->users) === 1 && $db->users[0]['email'] === 'owner@example.com', 'repeat bootstrap does not replace or add accounts');
checkBootstrap($db->released === 2, 'lock released after success and closed bootstrap');
