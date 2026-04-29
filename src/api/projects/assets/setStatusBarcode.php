<?php
require_once __DIR__ . '/../../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMENT_STATUS") or !isset($_POST['projects_id']) or !isset($_POST['assetsAssignments_status']) or !isset($_POST['text']) or strlen($_POST['text']) < 1) {
    finish(false, ["code" => "MISSING_PARAMS", "message" => "Missing required data for barcode dispatch"]);
}

try {
    $hasType = isset($_POST['type']) && strlen($_POST['type']) > 0 && $_POST['type'] !== 'UNKNOWN';
    $assetInstanceId = isset($_POST['instances_id']) && strlen($_POST['instances_id']) > 0 && is_numeric($_POST['instances_id']) ? $_POST['instances_id'] : $AUTH->data['instance']['instances_id'];

// See if Barcode is in database - scope to the selected asset instance via assets join
$DBLIB->where("assetsBarcodes.assetsBarcodes_value", $_POST['text']);
if ($hasType) $DBLIB->where("assetsBarcodes.assetsBarcodes_type", $_POST['type']);
$DBLIB->where("assetsBarcodes.assetsBarcodes_deleted", 0);
$DBLIB->where("assets.instances_id", $assetInstanceId);
$DBLIB->join("assets", "assets.assets_id=assetsBarcodes.assets_id", "LEFT");
$barcode = $DBLIB->getone("assetsBarcodes", ["assetsBarcodes.assets_id", "assetsBarcodes.assetsBarcodes_id"]);
if ($barcode and $barcode['assets_id'] != null) {
    $scan = [
        "assetsBarcodes_id" => $barcode['assetsBarcodes_id'],
        "users_userid" => $AUTH->data['users_userid'],
        "assetsBarcodesScans_timestamp" => date('Y-m-d H:i:s'),
        "locationsBarcodes_id" => (isset($_POST['locationType']) && $_POST['locationType'] == "barcode" && isset($_POST['location']) ? $_POST['location'] : null),
        "location_assets_id" => (isset($_POST['locationType']) && $_POST['locationType'] == "asset" && isset($_POST['location']) ? $_POST['location'] : null),
        "assetsBarcodes_customLocation" => (isset($_POST['locationType']) && $_POST['locationType'] == "Custom" && isset($_POST['location']) ? $_POST['location'] : null)
    ];
    $DBLIB->insert("assetsBarcodesScans", $scan);

    $DBLIB->where("assetsAssignments.assets_id", $barcode['assets_id']);
    $DBLIB->where("assetsAssignments.projects_id", $_POST['projects_id']);
    $DBLIB->where("assetsAssignments.assetsAssignments_deleted", 0);
    $DBLIB->where("projects.instances_id", $AUTH->data['instance']['instances_id']);
    $DBLIB->where("projects.projects_deleted", 0);
    $DBLIB->join("projects", "assetsAssignments.projects_id=projects.projects_id", "LEFT");
    $currentAssignment = $DBLIB->getOne("assetsAssignments", ["assetsAssignmentsStatus_id"]);

    if (!$currentAssignment) {
            // Find replacement candidates in the same project for this asset type
            $DBLIB->where("assets.assets_id", $barcode['assets_id']);
            $DBLIB->where("assets.instances_id", $assetInstanceId);
            $DBLIB->where('assets.assets_deleted', 0);
            $assetDetails = $DBLIB->getOne("assets", ["assetTypes_id"]);

            $swapCandidates = [];
            if ($assetDetails && $assetDetails['assetTypes_id']) {
                $DBLIB->where("assetsAssignments.projects_id", $_POST['projects_id']);
                $DBLIB->where("assetsAssignments.assetsAssignments_deleted", 0);
                $DBLIB->where("assets.assetTypes_id", $assetDetails['assetTypes_id']);
                $DBLIB->where('assets.assets_deleted', 0);
                $DBLIB->join("assets", "assetsAssignments.assets_id=assets.assets_id", "LEFT");
                $DBLIB->join("assetsAssignmentsStatus", "assetsAssignments.assetsAssignmentsStatus_id=assetsAssignmentsStatus.assetsAssignmentsStatus_id", "LEFT");
                $swapCandidates = $DBLIB->get("assetsAssignments", null, ["assetsAssignments_id", "assets.assets_id", "assets.assets_tag", "assets.asset_definableFields_1", "assetsAssignments.assetsAssignmentsStatus_id", "assetsAssignmentsStatus.assetsAssignmentsStatus_name"]);
            }

            finish(false, ["message" => "Asset not assigned to project", "code" => "NOTASSIGNED", "assets_id" => $barcode['assets_id'], "swapCandidates" => $swapCandidates]);
    }

    // If the assignment already has the requested status, treat this as success (no-op)
    if ((int)$currentAssignment['assetsAssignmentsStatus_id'] === (int)$_POST['assetsAssignments_status']) {
        $bCMS->auditLog("EDIT-STATUS", "assetsAssignments", "set to " . $_POST['assetsAssignments_status'] . " by barcode scan", $AUTH->data['users_userid'], null, $_POST['projects_id']);
        finish(true, null, ["assets_id" => $barcode['assets_id']]);
    }

    // Otherwise, update the status
    $DBLIB->where("assetsAssignments.assets_id", $barcode['assets_id']);
    $DBLIB->where("assetsAssignments.projects_id", $_POST['projects_id']);
    $DBLIB->where("assetsAssignments.assetsAssignments_deleted", 0);
    $DBLIB->where("projects.instances_id", $AUTH->data['instance']['instances_id']);
    $DBLIB->where("projects.projects_deleted", 0);
    $DBLIB->join("projects", "assetsAssignments.projects_id=projects.projects_id", "LEFT");
        $assignment = $DBLIB->update("assetsAssignments", ["assetsAssignmentsStatus_id" => $_POST['assetsAssignments_status']]);

    if (!$assignment or $DBLIB->count != 1) {
        finish(false, ["message" => "Asset not assigned to project", "code" => "NOTASSIGNED", "assets_id" => $barcode['assets_id']]);
    } else {
        $bCMS->auditLog("EDIT-STATUS", "assetsAssignments", "set to " . $_POST['assetsAssignments_status'] . " by barcode scan", $AUTH->data['users_userid'], null, $_POST['projects_id']);
        finish(true, null, ["assets_id" => $barcode['assets_id']]);
    }
    } else {
        finish(false, ["code" => "NOTFOUND", "message" => "Barcode not found"]);
    }
} catch (Throwable $e) {
    error_log("setStatusBarcode exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    finish(false, ["code" => "SERVER_ERROR", "message" => "Internal server error"]);
}

/** @OA\Post(
 *     path="/projects/assets/setStatusBarcode.php", 
 *     summary="Set Asset Assignment Status using Barcode", 
 *     description="Set the status for an asset assignment using a barcode  
 * Requires Instance Permission PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMENT_STATUS
 * ", 
 *     operationId="setAssetAssignmentStatusBarcode", 
 *     tags={"project_assets"}, 
 *     @OA\Response(
 *         response="200", 
 *         description="Success",
 *         @OA\MediaType(
 *             mediaType="application/json", 
 *             @OA\Schema( 
 *                 type="object", 
 *                 @OA\Property(
 *                     property="result", 
 *                     type="boolean", 
 *                     description="Whether the request was successful",
 *                 ),
 *             ),
 *         ),
 *     ), 
 *     @OA\Parameter(
 *         name="text",
 *         in="query",
 *         description="barcode value",
 *         required="true", 
 *         @OA\Schema(
 *             type="string"), 
 *         ), 
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         description="barcode type (optional - if not provided or set to UNKNOWN, searches by value only)",
 *         @OA\Schema(
 *             type="string"), 
 *         ), 
 *     @OA\Parameter(
 *         name="locationType",
 *         in="query",
 *         description="location type",
 *         required="true", 
 *         @OA\Schema(
 *             type="enum"), 
 *         ), 
 *     @OA\Parameter(
 *         name="projects_id",
 *         in="query",
 *         description="Project ID",
 *         required="true", 
 *         @OA\Schema(
 *             type="number"), 
 *         ), 
 *     @OA\Parameter(
 *         name="assetsAssignments_status",
 *         in="query",
 *         description="Status ID",
 *         required="true", 
 *         @OA\Schema(
 *             type="number"), 
 *         ), 
 * )
 */
