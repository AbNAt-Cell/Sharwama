#!/usr/bin/env php
<?php

/*
 * Laravel Cache Clearer Script (Web Accessible)
 * This script clears all Laravel caches via web browser
 * 
 * SECURITY WARNING: Delete this file after use or protect it with authentication!
 */

// Set execution time limit
set_time_limit(300);

$basePath = __DIR__;

// Check if we're in the right directory
if (!file_exists($basePath . '/artisan')) {
    die("❌ Error: Please place this script in your Laravel application root directory.");
}

// Simple authentication (CHANGE THIS PASSWORD!)
$password = 'clear123'; // ⚠️ CHANGE THIS PASSWORD!

// Check if password is provided
if (!isset($_GET['password']) || $_GET['password'] !== $password) {
    http_response_code(401);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Cache Clearer - Authentication Required</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            input { padding: 10px; margin: 10px 0; width: 100%; box-sizing: border-box; }
            button { padding: 12px 30px; background: #4CAF50; color: white; border: none; cursor: pointer; font-size: 16px; }
            button:hover { background: #45a049; }
            .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>🔒 Laravel Cache Clearer</h1>
        <div class="warning">
            ⚠️ <strong>Security Notice:</strong> This script should be deleted after use or protected with a strong password.
        </div>
        <form method="GET">
            <input type="password" name="password" placeholder="Enter password" required>
            <button type="submit">Clear Cache</button>
        </form>
    </body>
    </html>
    ');
}

// Start output buffering
ob_start();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Cache Clearer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .command {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
        }

        .command-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .output {
            color: #28a745;
            margin-top: 5px;
        }

        .error {
            color: #dc3545;
            margin-top: 5px;
        }

        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }

        .btn:hover {
            background: #c82333;
        }

        .timestamp {
            color: #999;
            font-size: 14px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🧹 Laravel Cache Clearer</h1>
        <p class="subtitle">Clearing all Laravel caches...</p>

        <div class="warning">
            ⚠️ <strong>Security Warning:</strong> Delete this file immediately after use or move it outside the public
            directory!
        </div>

        <?php
        // Change to Laravel root directory
        chdir($basePath);

        // Commands to execute
        $commands = [
            'Application Cache' => 'php artisan cache:clear',
            'Configuration Cache' => 'php artisan config:clear',
            'Route Cache' => 'php artisan route:clear',
            'View Cache' => 'php artisan view:clear',
            'Compiled Classes' => 'php artisan clear-compiled',
            'Event Cache' => 'php artisan event:clear',
        ];

        $allSuccess = true;

        foreach ($commands as $name => $command) {
            echo '<div class="command">';
            echo '<div class="command-title">📦 ' . htmlspecialchars($name) . '</div>';
            echo '<div style="color: #666; font-size: 14px;">$ ' . htmlspecialchars($command) . '</div>';

            // Execute command
            $output = [];
            $returnCode = 0;
            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo '<div class="output">✓ Success: ' . htmlspecialchars(implode("\n", $output)) . '</div>';
            } else {
                echo '<div class="error">✗ Error: ' . htmlspecialchars(implode("\n", $output)) . '</div>';
                $allSuccess = false;
            }

            echo '</div>';

            // Flush output to browser
            ob_flush();
            flush();
        }

        // Additional cleanup
        echo '<div class="command">';
        echo '<div class="command-title">🗑️ Clearing Bootstrap Cache Files</div>';

        $bootstrapCache = $basePath . '/bootstrap/cache';
        if (is_dir($bootstrapCache)) {
            $files = glob($bootstrapCache . '/*.php');
            $count = 0;
            foreach ($files as $file) {
                if (basename($file) !== '.gitignore' && is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
            echo '<div class="output">✓ Deleted ' . $count . ' bootstrap cache files</div>';
        } else {
            echo '<div class="output">ℹ️ Bootstrap cache directory not found</div>';
        }
        echo '</div>';

        if ($allSuccess) {
            echo '<div class="success">';
            echo '<strong>✓ All caches cleared successfully!</strong><br>';
            echo 'Your Laravel application caches have been cleared.';
            echo '</div>';
        } else {
            echo '<div class="warning">';
            echo '<strong>⚠️ Some operations failed</strong><br>';
            echo 'Please check the errors above and try running the commands manually via SSH.';
            echo '</div>';
        }
        ?>

        <div class="info">
            <strong>ℹ️ What was cleared:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Application cache (cache:clear)</li>
                <li>Configuration cache (config:clear)</li>
                <li>Route cache (route:clear)</li>
                <li>View cache (view:clear)</li>
                <li>Compiled classes (clear-compiled)</li>
                <li>Event cache (event:clear)</li>
                <li>Bootstrap cache files</li>
            </ul>
        </div>

        <div class="warning">
            <strong>🔒 Important Security Steps:</strong>
            <ol style="margin-left: 20px; margin-top: 10px;">
                <li>Delete this file immediately: <code>clear-cache.php</code></li>
                <li>Or move it outside your public directory</li>
                <li>Never commit this file to version control</li>
            </ol>
        </div>

        <a href="?password=<?php echo urlencode($password); ?>" class="btn">🔄 Clear Cache Again</a>

        <div class="timestamp">
            Executed at:
            <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>

</html>
<?php
ob_end_flush();
?>