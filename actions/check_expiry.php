<?php
require_once '../config/supabase.php';
require_once '../config/email_helper.php';

$today      = date('Y-m-d');
$in7days    = date('Y-m-d', strtotime('+7 days'));
$in1day     = date('Y-m-d', strtotime('+1 day'));

$access_7 = supabaseRequest(
    'Access_Request?select=request_id,expiry_date,user_id,dataset_id' .
    '&request_status=eq.Approved' .
    '&expiry_date=eq.' . $in7days .
    '&expiry_notified=is.false'
);

$access_1 = supabaseRequest(
    'Access_Request?select=request_id,expiry_date,user_id,dataset_id' .
    '&request_status=eq.Approved' .
    '&expiry_date=eq.' . $in1day .
    '&expiry_notified=is.false'
);

$access_rows = [];
if (is_array($access_7) && !isset($access_7['error'])) {
    $access_rows = array_merge($access_rows, $access_7);
}
if (is_array($access_1) && !isset($access_1['error'])) {
    $access_rows = array_merge($access_rows, $access_1);
}

$access_count = 0;
foreach ($access_rows as $row) {
    $user_result = supabaseRequest(
        'User?select=email,full_name&user_id=eq.' . $row['user_id']
    );
    $user = $user_result[0] ?? null;

    $dataset_result = supabaseRequest(
        'Dataset?select=name&dataset_id=eq.' . $row['dataset_id']
    );
    $dataset = $dataset_result[0] ?? null;

    if (!$user || !$dataset) continue;

    $days_left = (int) ((strtotime($row['expiry_date']) - strtotime($today)) / 86400);

    $sent = sendAccessExpiryEmail(
        $user['email'],
        $user['full_name'],
        $dataset['name'],
        $row['expiry_date'],
        $days_left
    );

    if ($sent) {
        supabaseRequest(
            'Access_Request?request_id=eq.' . $row['request_id'],
            'PATCH',
            ['expiry_notified' => true]
        );
        $access_count++;
    }
}

$in14days = date('Y-m-d', strtotime('+14 days'));
$in3days  = date('Y-m-d', strtotime('+3 days'));

$training_14 = supabaseRequest(
    'User?select=user_id,email,full_name,training_expiry' .
    '&training_completed=eq.true' .
    '&training_expiry=eq.' . $in14days
);

$training_3 = supabaseRequest(
    'User?select=user_id,email,full_name,training_expiry' .
    '&training_completed=eq.true' .
    '&training_expiry=eq.' . $in3days
);

$training_rows = [];
if (is_array($training_14) && !isset($training_14['error'])) {
    $training_rows = array_merge($training_rows, $training_14);
}
if (is_array($training_3) && !isset($training_3['error'])) {
    $training_rows = array_merge($training_rows, $training_3);
}

$training_count = 0;
foreach ($training_rows as $row) {
    $days_left = (int) ((strtotime($row['training_expiry']) - strtotime($today)) / 86400);

    $sent = sendTrainingExpiryEmail(
        $row['email'],
        $row['full_name'],
        $row['training_expiry'],
        $days_left
    );

    if ($sent) {
        $training_count++;
    }
}

if ($access_count > 0 || $training_count > 0) {
    supabaseRequest(
        'Audit_Log',
        'POST',
        [
            'user_id'      => null,
            'action'       => 'EXPIRY CHECK: ' . $access_count . ' access warnings sent, ' . $training_count . ' training warnings sent.',
            'target_table' => 'Access_Request',
            'target_id'    => 0
        ]
    );
}

echo 'Expiry check completed at ' . date('Y-m-d H:i:s') . '<br>';
echo 'Access expiry emails sent: '   . $access_count   . '<br>';
echo 'Training expiry emails sent: ' . $training_count . '<br>';
?>