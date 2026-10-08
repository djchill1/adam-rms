<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddGlobalCheckinStatusFlags extends AbstractMigration
{
    public function change(): void
    {
        $this->table('assetsAssignmentsStatus')
            ->addColumn('assetsAssignmentsStatus_dispatched', 'boolean', [
                'null' => false,
                'default' => false,
                'after' => 'assetsAssignmentsStatus_order',
            ])
            ->addColumn('assetsAssignmentsStatus_returned', 'boolean', [
                'null' => false,
                'default' => false,
                'after' => 'assetsAssignmentsStatus_dispatched',
            ])
            ->update();
    }
}
