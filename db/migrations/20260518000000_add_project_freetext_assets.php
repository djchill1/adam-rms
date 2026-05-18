<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddProjectFreetextAssets extends AbstractMigration
{
    public function change(): void
    {
        $this->table('assetsAssignments')
            ->addColumn('assetsAssignments_freetext', 'text', ['null' => true, 'after' => 'assetsAssignmentsStatus_id'])
            ->changeColumn('assets_id', 'integer', ['null' => true, 'signed' => true])
            ->update();
    }
}
