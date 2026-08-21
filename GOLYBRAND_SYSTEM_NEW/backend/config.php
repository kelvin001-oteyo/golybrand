<?php
declare(strict_types=1);

// PRODUCTION CONFIGURATION - Aiven MySQL
const DB_HOST='mysql-2d38ecee-oteyikelvin-a4a9.f.aivencloud.com;
const DB_PORT='10711';
const DB_NAME='defaultdb';
const DB_USER='avnadmin';
const DB_PASS='AVNS_LmHo4qAdg-C_JmXO5PJ';

// Secret used only when creating an administrator account.
const ADMIN_REGISTRATION_KEY='GolyBrandAdmin2026!';

$https=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly'=>true,
    'secure'=>$https,
    'samesite'=>'Lax',
]);
session_start();

function db(): PDO {
    static $p=null;
    if ($p instanceof PDO) return $p;
    
    // Aiven MySQL requires SSL
    // Using PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false for simplicity
    $p=new PDO(
        'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::MYSQL_ATTR_SSL_CA => '',
        ]
    );
    return $p;
}

function ensure_content_tables(): void {
    static $done=false;
    if ($done) return;
    $pdo=db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      content_type ENUM('forex','ebook','tiktok','award') NOT NULL,
      title VARCHAR(200) NOT NULL, description TEXT NULL, url VARCHAR(1000) NULL,
      sort_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX(content_type), INDEX(active), INDEX(sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS trivia_questions (
      id INT AUTO_INCREMENT PRIMARY KEY, question TEXT NOT NULL,
      option_a VARCHAR(500) NOT NULL, option_b VARCHAR(500) NOT NULL,
      option_c VARCHAR(500) NOT NULL, option_d VARCHAR(500) NOT NULL,
      correct_option ENUM('A','B','C','D') NOT NULL, explanation TEXT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX(active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done=true;
}

function input(): array {
    $x=json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($x) ? $x : $_POST;
}

function out(array $d, int $s=200): never {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_SLASHES);
    exit;
}

function username(?string $x): string {
    return strtolower(trim((string)$x));
}

function user(string $u): ?array {
    $q=db()->prepare('SELECT id,username,full_name,phone,sponsor,activated,mpesa_code,registration_expense,created_at,activated_at FROM users WHERE username=?');
    $q->execute([$u]);
    return $q->fetch() ?: null;
}

function need_user(): string {
    $u=$_SESSION['user'] ?? null;
    if (!$u) out(['success'=>false,'message'=>'Please log in.'],401);
    return (string)$u;
}

function calculate_balance(string $u): array {
    $q=db()->prepare('SELECT username FROM users WHERE sponsor=? AND activated=1');
    $q->execute([$u]);
    $l1=array_column($q->fetchAll(),'username');

    $l2=[];
    if ($l1) {
        $in=implode(',',array_fill(0,count($l1),'?'));
        $q=db()->prepare("SELECT username FROM users WHERE sponsor IN ($in) AND activated=1");
        $q->execute($l1);
        $l2=array_column($q->fetchAll(),'username');
    }

    $l3=[];
    if ($l2) {
        $in=implode(',',array_fill(0,count($l2),'?'));
        $q=db()->prepare("SELECT username FROM users WHERE sponsor IN ($in) AND activated=1");
        $q->execute($l2);
        $l3=array_column($q->fetchAll(),'username');
    }

    $earned=count($l1)*500 + count($l2)*300 + count($l3)*100;
    $q=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE username=? AND status<>'rejected'");
    $q->execute([$u]);
    $used=(float)$q->fetchColumn();

    return [
        'level1_count'=>count($l1),
        'level2_count'=>count($l2),
        'level3_count'=>count($l3),
        'earned'=>(float)$earned,
        'used'=>$used,
        'available'=>max(0,$earned-$used)
    ];
}
