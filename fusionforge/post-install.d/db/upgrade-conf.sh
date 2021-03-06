#!/bin/bash
# Upgrade PostgreSQL configuration
#
# Copyright (C) 2014  Inria (Sylvain Beucler)
#
# This file is part of FusionForge. FusionForge is free software;
# you can redistribute it and/or modify it under the terms of the
# GNU General Public License as published by the Free Software
# Foundation; either version 2 of the Licence, or (at your option)
# any later version.
#
# FusionForge is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with FusionForge; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

set -e

PREVVER=${1:-0.0}

# 5.3 -> 6.0
if [ $(php -r "print version_compare('$PREVVER', '5.3.50');") -eq -1 ]; then
    pg_hba=$(ls /etc/postgresql/*/*/pg_hba.conf /var/lib/pgsql/data/pg_hba.conf 2>/dev/null | tail -1)
    database_name=$(forge_get_config database_name)
    sed -i $pg_hba -e 's/\(^### \(BEGIN\|END\)\) GFORGE BLOCK/\1 FUSIONFORGE BLOCK/'
fi
