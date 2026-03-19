<?php

// Supabase API config
$SUPABASE_URL = "https://aovbucaislbgmgknvrnz.supabase.co";
$SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImFvdmJ1Y2Fpc2xiZ21na252cm56Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzMzOTQ5NTYsImV4cCI6MjA4ODk3MDk1Nn0.3vMLNjhNHu14N7FexmInT9EEtOZovpuUqrI-7Mqqe7Q"; 

function supabaseRequest($endpoint, $method = "GET", $data = null) {
    global $SUPABASE_URL, $SUPABASE_KEY;

    $url = $SUPABASE_URL . "/rest/v1/" . $endpoint;

    $headers = [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    if ($method === "PATCH") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    if ($method === "DELETE") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("cURL error: " . curl_error($ch));
    }

    curl_close($ch);

    return json_decode($response, true);
}