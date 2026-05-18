<?php
require_once __DIR__ . '/../../apiHeadSecure.php';

try {
    if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_ASSETS:CREATE:ASSIGN_AND_UNASSIGN") or !isset($_POST['projects_id']) or !isset($_POST['freetext']) or strlen($_POST['freetext']) < 1) {
        finish(false, ["message" => "Missing required parameters"]);
    }

    $DBLIB->where("projects.instances_id", $AUTH->data['instance_ids'], 'IN');
    $DBLIB->where("projects.projects_deleted", 0);
    $DBLIB->where("projects.projects_id", $_POST['projects_id']);
    $project = $DBLIB->getone("projects", ["projects_id", "projects_dates_deliver_start", "projects_dates_deliver_end", "projects_defaultDiscount", "projects_name"]);
    if (!$project) finish(false, ["message" => "Project not found"]);

    if ($project["projects_dates_deliver_start"] == null or $project["projects_dates_deliver_end"] == null or (strtotime($project["projects_dates_deliver_start"]) >= strtotime($project["projects_dates_deliver_end"]))) {
        finish(false, ["message" => "Please set the dates for the project before attempting to add items"]);
    }

    $insertData = [
        "projects_id" => $project['projects_id'],
        "assets_id" => null,
        "assetsAssignments_deleted" => 0,
        "assetsAssignments_timestamp" => date('Y-m-d H:i:s'),
        "assetsAssignmentsStatus_id" => null,
        "assetsAssignments_freetext" => $_POST['freetext'],
        "assetsAssignments_discount" => $project['projects_defaultDiscount']
    ];

    $insert = $DBLIB->insert("assetsAssignments", $insertData);
    if ($insert) {
        $bCMS->auditLog("CREATE-FREETEXT", "assetsAssignments", $insert . " - " . $_POST['freetext'], $AUTH->data['users_userid'], null, $project['projects_id']);
        finish(true, null, [
            "assetsAssignments_id" => $insert,
            "freetext" => $_POST['freetext']
        ]);
    }

    finish(false, ["message" => $DBLIB->getLastError() ?: "Cannot insert assignment"]);
} catch (Throwable $e) {
    error_log("addFreetext.php exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    finish(false, ["message" => "Unable to add free text item: " . $e->getMessage()]);
}

/** @OA\Post(
 *     path="/projects/assets/addFreetext.php", 
 *     summary="Add Free Text Item to Project", 
 *     description="Add a free text item to a project that can flow through dispatch states
Requires Instance Permission PROJECTS:PROJECT_ASSETS:CREATE:ASSIGN_AND_UNASSIGN
", 
 *     operationId="addFreetextToProject", 
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
 *         name="projects_id",
 *         in="query",
 *         description="Project ID",
 *         required="true", 
 *         @OA\Schema(
 *             type="number"), 
 *         ), 
 *     @OA\Parameter(
 *         name="freetext",
 *         in="query",
 *         description="Free text description",
 *         required="true", 
 *         @OA\Schema(
 *             type="string"), 
 *         ), 
 * )
 */
