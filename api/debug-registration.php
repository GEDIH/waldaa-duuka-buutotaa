<?php
/**
 * Debug Registration Endpoint
 * Shows exactly what happens when you try to register a member.
 * DELETE THIS FILE after debugging — it exposes sensitive info.
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<title>Registration Debug</title>
<style>
body{font-family:monospace;background:#1e1e2e;color:#cdd6f4;padding:2rem;}
h2{color:#89b4fa;border-bottom:2px solid #45475a;padding-bottom:.5rem;}
.ok{color:#a6e3a1;} .err{color:#f38ba8;} .warn{color:#f9e2af;}
pre{background:#181825;padding:1rem;border-radius:6px;overflow-x:auto;}
table{border-collapse:collapse;width:100%;margin:1rem 0;}
td,th{border:1px solid #45475a;padding:.5rem;text-align:left;}
th{background:#313244;color:#89dceb;}
</style>
</head>
<body>
<h1>🔍 Registration Debug</h1>

<?php
// ── 1. Check .env file ────────────────────────────────────────────────────────
echo "<h2>1. Environment (.env)</h2>";
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    echo "<p class='err'>✘ .env file NOT FOUND at: {$envFile}</p>";
    echo "<p class='warn'>⚠ Run install.php first or create .env manually</p>";
} else {
    echo "<p class='ok'>✔ .env exists</p>";
    echo "<table><tr><th>Key</th><th>Value</th></tr>";
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // Mask password
        if (str_contains(strtolower($k), 'pass')) $v = str_repeat('*', strlen($v));
        echo "<tr><td>{$k}</td><td>{$v}</td></tr>";
    }
    echo "</table>";
}

// ── 2. Database connection ────────────────────────────────────────────────────
echo "<h2>2. Database Connection</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    $pdo = Database::getInstance()->getConnection();
    $row = $pdo->query("SELECT VERSION() AS ver, DATABASE() AS db")->fetch();
    echo "<p class='ok'>✔ Connected to MySQL {$row['ver']}</p>";
    echo "<p class='ok'>✔ Using database: <strong>{$row['db']}</strong></p>";
} catch (Throwable $e) {
    echo "<p class='err'>✘ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='warn'>⚠ Fix your .env credentials or run install.php</p>";
    exit;
}

// ── 3. Check tables exist ─────────────────────────────────────────────────────
echo "<h2>3. Required Tables</h2>";
$required = ['users', 'members', 'centers', 'audit_logs', 'registration_log'];
$existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$missing  = array_diff($required, $existing);

if (empty($missing)) {
    echo "<p class='ok'>✔ All required tables exist</p>";
} else {
    echo "<p class='err'>✘ Missing tables: " . implode(', ', $missing) . "</p>";
    echo "<p class='warn'>⚠ Run install.php or: <code>mysql -u root -p &lt; sql/setup.sql</code></p>";
    exit;
}

// ── 4. Test INSERT ────────────────────────────────────────────────────────────
echo "<h2>4. Test Member INSERT</h2>";
try {
    require_once __DIR__ . '/services/RegistrationService.php';
    
    $testData = [
        'fullName'    => 'Debug Test User',
        'username'    => 'debug.test.' . time(),
        'mobilePhone' => '+251911' . rand(100000, 999999),
        'password'    => 'Test1234',
        'email'       => '',
        'quickRegistration' => true,
    ];
    
    echo "<p>Attempting to register test user:</p><pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";
    
    $service = new RegistrationService();
    $result  = $service->registerMember($testData);
    
    if ($result['success']) {
        echo "<p class='ok'>✔ Registration SUCCESS!</p>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
        
        // Verify in DB
        $memberId = $result['data']['member_id'];
        $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
        $stmt->execute([$memberId]);
        $row = $stmt->fetch();
        
        if ($row) {
            echo "<p class='ok'>✔ Member row exists in database:</p>";
            echo "<table><tr><th>Column</th><th>Value</th></tr>";
            foreach ($row as $k => $v) {
                echo "<tr><td>{$k}</td><td>" . htmlspecialchars($v ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
            
            // Clean up test data
            $pdo->exec("DELETE FROM members WHERE member_id = '{$memberId}'");
            $pdo->exec("DELETE FROM users WHERE member_id = '{$memberId}'");
            $pdo->exec("DELETE FROM registration_log WHERE member_id = '{$memberId}'");
            echo "<p class='warn'>⚠ Test data cleaned up</p>";
        } else {
            echo "<p class='err'>✘ Member row NOT FOUND in database after insert!</p>";
        }
        
    } else {
        echo "<p class='err'>✘ Registration FAILED</p>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    }
    
} catch (Throwable $e) {
    echo "<p class='err'>✘ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// ── 5. Check logs ─────────────────────────────────────────────────────────────
echo "<h2>5. Error Logs</h2>";
$logFiles = [
    dirname(__DIR__) . '/logs/php_errors.log',
    dirname(__DIR__) . '/logs/registration_errors.log',
    __DIR__ . '/logs/registration_errors.log',
];
$foundLogs = false;
foreach ($logFiles as $log) {
    if (file_exists($log) && filesize($log) > 0) {
        $foundLogs = true;
        echo "<h3>" . basename($log) . "</h3>";
        echo "<pre>" . htmlspecialchars(file_get_contents($log)) . "</pre>";
    }
}
if (!$foundLogs) {
    echo "<p class='ok'>✔ No error logs (good sign)</p>";
}

?>

<hr>
<p class='warn'><strong>⚠ DELETE THIS FILE</strong> after debugging — it exposes DB structure and credentials.</p>
</body>
</html>
