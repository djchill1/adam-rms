<?php
require_once __DIR__ . '/../common/headSecure.php';

if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ["TITLE" => "Scan Asset Barcodes", "BREADCRUMB" => false];

$DBLIB->where("instances_id", $AUTH->data['instance']['instances_id']);
$DBLIB->where("assetsAssignmentsStatus_deleted", 0);
$DBLIB->orderBy("assetsAssignmentsStatus_order", "ASC");
$DBLIB->orderBy("assetsAssignmentsStatus_id", "ASC");
$globalCheckInStatuses = $DBLIB->get("assetsAssignmentsStatus");
$PAGEDATA['GLOBAL_CHECKIN_STATUSES'] = is_array($globalCheckInStatuses) ? $globalCheckInStatuses : [];
$PAGEDATA['GLOBAL_CHECKIN_RETURNED_STATUSES'] = array_values(array_filter(
    $PAGEDATA['GLOBAL_CHECKIN_STATUSES'],
    function ($status) {
        return (int)$status['assetsAssignmentsStatus_returned'] === 1;
    }
));
$PAGEDATA['GLOBAL_CHECKIN_HAS_DISPATCHED'] = count(array_filter(
    $PAGEDATA['GLOBAL_CHECKIN_STATUSES'],
    function ($status) {
        return (int)$status['assetsAssignmentsStatus_dispatched'] === 1;
    }
)) > 0;
$PAGEDATA['GLOBAL_CHECKIN_CAN_UPDATE'] = $AUTH->instancePermissionCheck("PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMENT_STATUS");

echo $TWIG->render('maintenance/barcode.twig', $PAGEDATA);
