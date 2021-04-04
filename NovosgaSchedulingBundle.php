<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SchedulingBundle;

use Novosga\Module\BaseModule;

class NovosgaSchedulingBundle extends BaseModule
{
    public function getIconName()
    {
        return 'calendar';
    }

    public function getDisplayName()
    {
        return 'module.name';
    }

    public function getHomeRoute()
    {
        return 'novosga_scheduling_index';
    }
}
