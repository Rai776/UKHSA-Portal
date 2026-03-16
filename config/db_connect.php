<?php
// Database connection for Supabase (Transaction Pooler)

// Supabase connection info
$host = "aws-1-eu-central-1.pooler.supabase.com"; // Transaction Pooler host
$port = "6543";                                     // Pooler port
$dbname = "postgres";                               // Database name
$user = "postgres.aovbucaislbgmgknvrnz";           // Database user
$password = "sdadhgasdgasdsad123";                        // Replace with your Supabase password

// Connection string with SSL required
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require";

// Connect to PostgreSQL
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

echo "Connected successfully to Supabase Transaction Pooler!";