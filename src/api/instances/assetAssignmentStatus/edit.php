<?php
require_once __DIR__ . '/../../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT") || !isset($_POST['statusId'])) finish(false);

$changes = [];
if (isset($_POST['statusName'])) {
    $changes["assetsAssignmentsStatus_name"] = $_POST['statusName'];
}
foreach ([
    "dispatched" => "assetsAssignmentsStatus_dispatched",
    "returned" => "assetsAssignmentsStatus_returned",
] as $input => $column) {
    if (isset($_POST[$input])) {
        if (!in_array((string)$_POST[$input], ["0", "1"], true)) {
            finish(false, ["code" => "INVALID-STATUS-FLAG", "message" => "Invalid asset status flag"]);
        }
        $changes[$column] = (int)$_POST[$input];
    }
}
if (!$changes) finish(false, ["code" => "MISSING-STATUS-CHANGES", "message" => "No asset status changes provided"]);

$DBLIB->where("instances_id", $AUTH->data['instance']['instances_id']);
$DBLIB->where("assetsAssignmentsStatus_deleted", 0);
$DBLIB->where("assetsAssignmentsStatus_id", $_POST['statusId']);
$updateQuery = $DBLIB->update("assetsAssignmentsStatus", $changes);

if (!$updateQuery) finish(false, ["code" => "UPDATE-STATUS-FAIL", "message"=> "Could not Update asset status"]);
finish(true);

/** @OA\Post(
 *     path="/instances/assetAssignmentStatus/edit.php", 
 *     summary="Edit Asset Assignment Status", 
 *     description="Edit an asset assignment status  
Requires Instance Permission BUSINESS:BUSINESS_SETTINGS:EDIT
", 
 *     operationId="editAssetAssignmentStatus", 
 *     tags={"assetAssignmentStatus"}, 
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
 *                 @OA\Property(
 *                     property="response", 
 *                     type="array", 
 *                     description="A null Array",
 *                 ),
 *             ),
 *         ),
 *     ), 
 *     @OA\Response(
 *         response="default", 
 *         description="Error",
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
 *         name="statusId",
 *         in="query",
 *         description="The status id",
 *         required="true", 
 *         @OA\Schema(
 *             type="integer"), 
 *         ), 
 *     @OA\Parameter(
 *         name="statusName",
 *         in="query",
 *         description="The status name",
 *         required="false",
 *         @OA\Schema(
 *             type="string"), 
 *         ), 
 *     @OA\Parameter(
 *         name="dispatched",
 *         in="query",
 *         description="Whether this status is eligible for Global Check In",
 *         required="false",
 *         @OA\Schema(type="boolean"),
 *     ),
 *     @OA\Parameter(
 *         name="returned",
 *         in="query",
 *         description="Whether this status is a Global Check In destination",
 *         required="false",
 *         @OA\Schema(type="boolean"),
 *     ),
 * )
 */