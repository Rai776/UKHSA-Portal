<?php
require_once '../config/db_connect.php';
require_once '../config/email_helper.php';

$access_query = pg_query($conn, '
    SELECT
        ar.request_id,
        ar.expiry_date,
        (ar.expiry_date - CURRENT_DATE) AS days_left,
        u.email,
        u.full_name,
        d.name AS dataset_name
    FROM "Access_Request" ar
    JOIN "User" u    ON ar.user_id    = u.user_id
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    WHERE ar.request_status  = \'Approved\'
    AND   ar.expiry_date     IS NOT NULL
    AND   ar.expiry_notified IS NOT TRUE
    AND   (ar.expiry_date - CURRENT_DATE) IN (7, 1)
');

$access_count = 0;
if ($access_query) {
    while ($row = pg_fetch_assoc($access_query)) {
        $days_left = intval($row['days_left']);

        $sent = sendAccessExpiryEmail(
            $row['email'],
            $row['full_name'],
            $row['dataset_name'],
            $row['expiry_date'],
            $days_left
        );

        if ($sent) {
            pg_query_params($conn,
                'UPDATE "Access_Request" SET expiry_notified = true WHERE request_id = $1',
                [$row['request_id']]
            );
            $access_count++;
        }
    }
}

$training_query = pg_query($conn, '
    SELECT
        u.user_id,
        u.email,
        u.full_name,
        u.training_expiry,
        (u.training_expiry - CURRENT_DATE) AS days_left
    FROM "User" u
    WHERE u.training_completed = true
    AND   u.training_expiry    IS NOT NULL
    AND   (u.training_expiry - CURRENT_DATE) IN (14, 3)
');

$training_count = 0;
if ($training_query) {
    while ($row = pg_fetch_assoc($training_query)) {
        $days_left = intval($row['days_left']);

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
}

if ($access_count > 0 || $training_count > 0) {
    pg_query_params($conn,
        'INSERT INTO "Audit_Log" (user_id, action, target_table, target_id) VALUES ($1, $2, $3, $4)',
        [
            NULL,
            'EXPIRY CHECK: ' . $access_count . ' access warnings sent, ' . $training_count . ' training warnings sent.',
            'Access_Request',
            0
        ]
    );
}

echo 'Expiry check completed at ' . date('Y-m-d H:i:s') . '<br>';
echo 'Access expiry emails sent: ' . $access_count . '<br>';
echo 'Training expiry emails sent: ' . $training_count . '<br>';
?>