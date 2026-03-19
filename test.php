<?php
require "config/supabase.php";

$data = supabaseRequest("User");

echo "<pre>";
print_r($data);