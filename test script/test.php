<?php
/**
 * Manual test of Config, Crypto, UserStore and SessionStore.
 * Run from the project root:   php "test script/test.php"
 * Exit code 0 = everything OK, 1 = at least one FAIL (usable later in CI).
 */
declare(strict_types=1);

$root = dirname(__DIR__, 1);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/api/config.php';
require_once $root . '/src/api/crypto.php';
require_once $root . '/src/api/user_store.php';
require_once $root . '/src/api/session_store.php';
require_once $root . '/src/api/check_time.php';

// ---------------------------------------------------------------- expected values
// Taken from seed/inject_report.json (admin account + one seeded session)
const ADMIN_EMAIL           = 'admin@jobboard.test';
const ADMIN_PASSWORD        = 'RABr-1VjA-rnVg!';
const ADMIN_EMAIL_INDEX_HEX = 'ffefe8c0bb1f65048dc115e1';   // first 12 bytes shown in the report
const SEEDED_COOKIE         = 'vHquilA0mXnNGMeSHTtkr0bVWWQ1SfkUNfQDXB3OdN8';

// ---------------------------------------------------------------- tiny test helper
$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    printf("  [%s] %s%s\n", $ok ? ' OK ' : 'FAIL', $label, $detail !== '' ? "  ($detail)" : '');
}

function section(string $title): void
{
    echo "\n== $title\n";
}

// ================================================================ 1. Config
section('Config');
check('HMAC key is 32 bytes', strlen(get_HMAC_key()) === 32);
check('Master key is 32 bytes', strlen(get_MASTER_key()) === 32);

$pdo = get_db_config();
$version = (string) $pdo->query('SELECT version()')->fetchColumn();
check('DB connection', str_starts_with($version, 'PostgreSQL'), strtok($version, ',') ?: '');
$nbUsers = (int) $pdo->query('SELECT count(*) FROM users')->fetchColumn();
check('users table is seeded', $nbUsers > 0, "$nbUsers users");

// ================================================================ 2. Crypto
section('Crypto');

$emailIndex = get_hmask_index_mail(ADMIN_EMAIL);
check('email index is 32 raw bytes', strlen($emailIndex) === 32);
check('email index matches Python', str_starts_with(bin2hex($emailIndex), ADMIN_EMAIL_INDEX_HEX),
      substr(bin2hex($emailIndex), 0, 24) . '…');
check('email normalization (uppercase + spaces)',
      get_hmask_index_mail('  ADMIN@JobBoard.TEST ') === $emailIndex);

$tokenA = generate_new_token();
$tokenB = generate_new_token();
check('new token is 64 hex characters', strlen($tokenA) === 64 && ctype_xdigit($tokenA), $tokenA);
check('two new tokens are different', $tokenA !== $tokenB);

check('token hash is 32 raw bytes', strlen(hash_token($tokenA)) === 32);

// The seeded session may be expired, so look it up WITHOUT the expiry filter:
// this only checks that PHP hashes a Python token exactly like Python did.
$stmt = $pdo->prepare('SELECT count(*) FROM session WHERE token_hash = :h');
$stmt->bindValue(':h', hash_token(SEEDED_COOKIE), PDO::PARAM_LOB);
$stmt->execute();
check('seeded cookie hash found in DB (PHP = Python)', (int) $stmt->fetchColumn() === 1);

// ================================================================ 3. UserStore
section('UserStore');

$user = find_user_by_email_index($pdo, $emailIndex);
check('admin found by email index', is_array($user));
check('admin has role admin', ($user['role'] ?? null) === 'admin');
check('unknown email returns null',
      find_user_by_email_index($pdo, get_hmask_index_mail('nobody@nowhere.test')) === null);

$hash = (string) ($user['password_hash'] ?? '');
check('right password accepted', check_password(ADMIN_PASSWORD, $hash));
check('wrong password rejected', check_password('wrong-password', $hash) === false);

// ================================================================ 4. SessionStore
section('SessionStore');

// Use a NEW token: the seeded cookie already has a session row, and token_hash is UNIQUE.
$sessionId = create_session($pdo, (string) $user['user_id'], hash_token($tokenA), 'test-script');
check('session created', $sessionId !== '', $sessionId);

$session = find_valid_session($pdo, hash_token($tokenA));
check('valid session found', is_array($session));
check('session belongs to admin', ($session['user_id'] ?? null) === $user['user_id']);
check('wrong token returns null', find_valid_session($pdo, hash_token('wrong')) === null);

// Cleanup: don't leave test sessions behind
$del = $pdo->prepare('DELETE FROM session WHERE session_id = :id');
$del->bindValue(':id', $sessionId);
$del->execute();
check('test session deleted', $del->rowCount() === 1);

// ================================================================ 4. SessionStore
section('Failed logins');
$uid = (string) $user['user_id'];
reset_failed_logins($pdo, $uid);
for ($i = 1; $i <= 5; $i++) {
    check("failure $i counted", record_failed_login($pdo, $uid) === $i);
}
$locked = find_user_by_email_index($pdo, $emailIndex);
check('locked after 5 failures', isInFuture($locked['bloque_jusqua']));
reset_failed_logins($pdo, $uid);
$clean = find_user_by_email_index($pdo, $emailIndex);
check('reset clears counter + lock',
      (int) $clean['echecs_connexion'] === 0 && $clean['bloque_jusqua'] === null);

// ================================================================ summary
printf("\n%d passed, %d failed\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);

