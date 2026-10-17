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
$dbHost = "162.214.80.27";

// Database name
$dbName = "jewrzsmy_panchved";

// Database username
$dbUser = "jewrzsmy_panchveduser";

// Database password
$dbPass = '$hr1dhar@321';


/*
|--------------------------------------------------------------------------
| CREATE DATABASE CONNECTION
|--------------------------------------------------------------------------
|
| mysqli_connect() is used to connect PHP to MySQL.
|
| Parameters:
| 1. Host Name / IP Address
| 2. Username
| 3. Password
| 4. Database Name
|
*/

$connection1 = mysqli_connect(
    $dbHost,
    $dbUser,
    $dbPass,
    $dbName
);


/*
|--------------------------------------------------------------------------
| CHECK IF CONNECTION IS SUCCESSFUL
|--------------------------------------------------------------------------
|
| If connection fails, display the error and stop execution.
|
*/

if (!$connection1)
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

mysqli_set_charset(
    $connection1,
    "utf8mb4"
);

// Standardize application & database timezone to Indian Standard Time (IST / UTC+05:30)
date_default_timezone_set('Asia/Kolkata');
mysqli_query($connection1, "SET time_zone = '+05:30'");


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