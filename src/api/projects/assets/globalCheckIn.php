<?php
require_once __DIR__ . '/../../apiHeadSecure.php';

if (
    !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMENT_STATUS")
    || !isset($_POST['assets_id'], $_POST['assetsAssignmentsStatus_id'])
    || filter_var($_POST['assets_id'], FILTER_VALIDATE_INT) === false
    || filter_var($_POST['assetsAssignmentsStatus_id'], FILTER_VALIDATE_INT) === false
) {
    finish(false, ["code" => "MISSING_PARAMS", "message" => "Missing or invalid data for global check in"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$assetId = (int)$_POST['assets_id'];
$destinationStatusId = (int)$_POST['assetsAssignmentsStatus_id'];

try {
    $DBLIB->where("instances_id", $instanceId);
    $DBLIB->where("assetsAssignmentsStatus_deleted", 0);
    $DBLIB->where("assetsAssignmentsStatus_dispatched", 1);
    if (!$DBLIB->getOne("assetsAssignmentsStatus", ["assetsAssignmentsStatus_id"])) {
        finish(false, ["code" => "NO_DISPATCHED_STATUSES", "message" => "Global Check In is unavailable because no asset status is configured as Dispatched"]);
    }

    $DBLIB->where("assetsAssignmentsStatus_id", $destinationStatusId);
    $DBLIB->where("instances_id", $instanceId);
    $DBLIB->where("assetsAssignmentsStatus_deleted", 0);
    $DBLIB->where("assetsAssignmentsStatus_returned", 1);
    $destinationStatus = $DBLIB->getOne("assetsAssignmentsStatus", ["assetsAssignmentsStatus_id"]);
    if (!$destinationStatus) {
        finish(false, ["code" => "INVALID_RETURNED_STATUS", "message" => "The selected destination is not configured as a Returned status"]);
    }

    $DBLIB->where("assets.assets_id", $assetId);
    $DBLIB->where("assets.instances_id", $instanceId);
    $DBLIB->where("assets.assets_deleted", 0);
    $DBLIB->join("assetTypes", "assets.assetTypes_id=assetTypes.assetTypes_id", "LEFT");
    $DBLIB->join("assetCategories", "assetTypes.assetCategories_id=assetCategories.assetCategories_id", "LEFT");
    $DBLIB->join("assetCategoriesGroups", "assetCategories.assetCategoriesGroups_id=assetCategoriesGroups.assetCategoriesGroups_id", "LEFT");
    $DBLIB->join("manufacturers", "assetTypes.manufacturers_id=manufacturers.manufacturers_id", "LEFT");
    $asset = $DBLIB->getOne("assets", [
        "assets.assets_id",
        "assets.assets_tag",
        "assetTypes.assetTypes_name",
        "assetTypes.assetTypes_id",
        "assetCategories.assetCategories_name",
        "assetCategoriesGroups.assetCategoriesGroups_name",
        "manufacturers.manufacturers_name",
    ]);
    if (!$asset) {
        finish(false, ["code" => "ASSET_NOT_FOUND", "message" => "Asset not found in this instance"]);
    }

    $DBLIB->where("assets.assets_id", $assetId);
    $DBLIB->where("assets.instances_id", $instanceId);
    $DBLIB->where("assets.assets_deleted", 0);
    $DBLIB->where("assetsAssignments.assetsAssignments_deleted", 0);
    $DBLIB->where("projects.instances_id", $instanceId);
    $DBLIB->where("projects.projects_deleted", 0);
    $DBLIB->where("projects.projects_archived", 0);
    $DBLIB->where("projectsStatuses.projectsStatuses_assetsReleased", 0);
    $DBLIB->join("assetsAssignments", "assetsAssignments.assets_id=assets.assets_id", "LEFT");
    $DBLIB->join("projects", "assetsAssignments.projects_id=projects.projects_id", "LEFT");
    $DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
    $DBLIB->join("assetsAssignmentsStatus", "assetsAssignments.assetsAssignmentsStatus_id=assetsAssignmentsStatus.assetsAssignmentsStatus_id", "LEFT");
    $assignments = $DBLIB->get("assets", null, [
        "assetsAssignments.assetsAssignments_id",
        "assetsAssignments.projects_id",
        "assetsAssignments.assetsAssignmentsStatus_id",
        "assetsAssignmentsStatus.assetsAssignmentsStatus_dispatched",
        "assetsAssignmentsStatus.assetsAssignmentsStatus_returned",
        "assetsAssignmentsStatus.assetsAssignmentsStatus_deleted",
        "projects.projects_name",
    ]);

    if (!$assignments) {
        finish(false, ["code" => "NO_ACTIVE_PROJECT", "message" => "This asset is not assigned to an active project and cannot be checked in globally"]);
    }

    $eligibleAssignments = array_values(array_filter($assignments, function ($assignment) {
        return (int)$assignment['assetsAssignmentsStatus_dispatched'] === 1
            && (int)$assignment['assetsAssignmentsStatus_deleted'] === 0;
    }));

    if (count($eligibleAssignments) === 0) {
        $alreadyReturned = count(array_filter($assignments, function ($assignment) {
            return (int)$assignment['assetsAssignmentsStatus_returned'] === 1
                && (int)$assignment['assetsAssignmentsStatus_deleted'] === 0;
        })) > 0;
        if ($alreadyReturned) {
            finish(false, ["code" => "ALREADY_RETURNED", "message" => "This asset is already in a Returned status"]);
        }
        finish(false, ["code" => "NOT_DISPATCHED", "message" => "This asset is not currently in a Dispatched status"]);
    }

    if (count($eligibleAssignments) > 1) {
        finish(false, ["code" => "AMBIGUOUS_ACTIVE_ASSIGNMENT", "message" => "This asset is dispatched to more than one active project and cannot be checked in automatically"]);
    }

    $assignment = $eligibleAssignments[0];
    if ((int)$assignment['assetsAssignmentsStatus_id'] !== $destinationStatusId) {
        $DBLIB->startTransaction();
        $DBLIB->where("assetsAssignments.assetsAssignments_id", $assignment['assetsAssignments_id']);
        $DBLIB->where("assetsAssignments.assets_id", $assetId);
        $DBLIB->where("assetsAssignments.projects_id", $assignment['projects_id']);
        $DBLIB->where("assetsAssignments.assetsAssignmentsStatus_id", $assignment['assetsAssignmentsStatus_id']);
        $DBLIB->where("assetsAssignments.assetsAssignments_deleted", 0);
        $DBLIB->where("assetsAssignmentsStatus.instances_id", $instanceId);
        $DBLIB->where("assetsAssignmentsStatus.assetsAssignmentsStatus_deleted", 0);
        $DBLIB->where("assetsAssignmentsStatus.assetsAssignmentsStatus_dispatched", 1);
        $DBLIB->where("assets.instances_id", $instanceId);
        $DBLIB->where("assets.assets_deleted", 0);
        $DBLIB->where("projects.instances_id", $instanceId);
        $DBLIB->where("projects.projects_deleted", 0);
        $DBLIB->where("projects.projects_archived", 0);
        $DBLIB->where("projectsStatuses.projectsStatuses_assetsReleased", 0);
        $DBLIB->join("assets", "assetsAssignments.assets_id=assets.assets_id", "LEFT");
        $DBLIB->join("projects", "assetsAssignments.projects_id=projects.projects_id", "LEFT");
        $DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
        $DBLIB->join("assetsAssignmentsStatus", "assetsAssignments.assetsAssignmentsStatus_id=assetsAssignmentsStatus.assetsAssignmentsStatus_id", "LEFT");
        $updated = $DBLIB->update("assetsAssignments", [
            "assetsAssignmentsStatus_id" => $destinationStatusId,
        ], 1);

        if (!$updated || (int)$DBLIB->count !== 1) {
            $DBLIB->rollback();
            finish(false, ["code" => "ASSIGNMENT_CHANGED", "message" => "The asset assignment changed while it was being checked in. Please scan it again"]);
        }
        $DBLIB->commit();
        $bCMS->auditLog(
            "EDIT-STATUS",
            "assetsAssignments",
            "set to " . $destinationStatusId . " by global barcode check in",
            $AUTH->data['users_userid'],
            null,
            $assignment['projects_id']
        );
    }

    finish(true, null, [
        "asset" => $asset,
        "project" => [
            "projects_id" => $assignment['projects_id'],
            "projects_name" => $assignment['projects_name'],
        ],
        "assetsAssignments_id" => $assignment['assetsAssignments_id'],
        "assetsAssignmentsStatus_id" => $destinationStatusId,
    ]);
} catch (Throwable $e) {
    error_log("globalCheckIn exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    finish(false, ["code" => "SERVER_ERROR", "message" => "Internal server error"]);
}

/** @OA\Post(
 *     path="/projects/assets/globalCheckIn.php",
 *     summary="Check in a dispatched asset across active projects",
 *     description="Move an asset from its active project's Dispatched status to a configured Returned status. Requires instance permission PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMENT_STATUS.",
 *     operationId="globalCheckInAsset",
 *     tags={"project_assets"},
 *     @OA\Response(
 *         response="200",
 *         description="Check-in result",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(ref="#/components/schemas/SimpleResponse"),
 *         ),
 *     ),
 *     @OA\Parameter(
 *         name="assets_id",
 *         in="query",
 *         description="Asset ID returned by the barcode lookup",
 *         required="true",
 *         @OA\Schema(type="integer"),
 *     ),
 *     @OA\Parameter(
 *         name="assetsAssignmentsStatus_id",
 *         in="query",
 *         description="Configured Returned destination status ID",
 *         required="true",
 *         @OA\Schema(type="integer"),
 *     ),
 * )
 */
