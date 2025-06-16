<?php

$mysqli = mysqli_connect("127.0.0.1", "root", "", "blipp", 3307);

// Check connection
if ($mysqli === false) {
    error_log("Failed to connect to MySQL: " . mysqli_connect_error());
    // Do not proceed with any database operations if connection failed
}
?>