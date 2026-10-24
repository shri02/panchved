<?php

/*
|--------------------------------------------------------------------------
| DATABASE CONFIGURATION
|--------------------------------------------------------------------------
|
| Store your database credentials in variables.
| This makes the code easier to read and update later.
|
*/

// Database server IP address or hostname
$dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? "p:162.214.80.27"));

// Database name
$dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? "jewrzsmy_panchved"));

// Database username
$dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? "jewrzsmy_panchveduser"));

// Database password
$dbPass = (getenv('DB_PASS') !== false && getenv('DB_PASS') !== '') 
    ? getenv('DB_PASS') 
    : ($_ENV['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? '$hr1dhar@321'));


/*
|--------------------------------------------------------------------------
| CREATE DATABASE CONNECTION
|--------------------------------------------------------------------------
|
| Connect PHP to MySQL with support for Azure SSL and standard connections.
|
*/

$connection1 = mysqli_init();
if ($connection1) {
    // Enable SSL if connecting to Azure or DB_SSL flag is set
    if (getenv('DB_SSL') === 'true' || strpos($dbHost, 'azure.com') !== false) {
        mysqli_ssl_set($connection1, NULL, NULL, NULL, NULL, NULL);
        $connected = @mysqli_real_connect($connection1, $dbHost, $dbUser, $dbPass, $dbName, 3306, NULL, MYSQLI_CLIENT_SSL);
    } else {
        $connected = @mysqli_real_connect($connection1, $dbHost, $dbUser, $dbPass, $dbName);
    }

    if (!$connected) {
        // Fallback to standard mysqli_connect
        $connection1 = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
    }
} else {
    $connection1 = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
}


/*
|--------------------------------------------------------------------------
| CHECK IF CONNECTION IS SUCCESSFUL
|--------------------------------------------------------------------------
|
| If connection fails, display the error and stop execution.
|
*/

if (!$connection1 || (is_object($connection1) && mysqli_connect_errno()))
{
    die(
        "Database Connection Failed: " .
        mysqli_connect_error()
    );
}


/*
|--------------------------------------------------------------------------
| SET CHARACTER ENCODING
|--------------------------------------------------------------------------
|
| utf8mb4 supports:
| - English
| - Marathi
| - Hindi
| - Emojis
| - Special Characters
|
| Recommended for all modern applications.
|
*/

if ($connection1 && !mysqli_connect_errno()) {
    @mysqli_set_charset($connection1, "utf8mb4");
    // Standardize application & database timezone to Indian Standard Time (IST / UTC+05:30)
    date_default_timezone_set('Asia/Kolkata');
    @mysqli_query($connection1, "SET time_zone = '+05:30'");
}


/*
|--------------------------------------------------------------------------
| CONNECTION SUCCESS MESSAGE (OPTIONAL)
|--------------------------------------------------------------------------
|
| Uncomment this line only for testing.
| Remove or comment it in production.
|
*/

// echo "Database Connected Successfully";


/*
|--------------------------------------------------------------------------
| HOW TO USE THIS FILE
|--------------------------------------------------------------------------
|
| In any API file, simply write:
|
| include 'database.php';
|
| Then use:
|
| $connection1
|
| for all database queries.
|
*/


/*
|--------------------------------------------------------------------------
| EXAMPLE QUERY
|--------------------------------------------------------------------------
|
| $result = mysqli_query(
|     $connection1,
|     "SELECT * FROM employees"
| );
|
*/

?>