<?php

namespace App\Models;

use App\Core\Database;

abstract class Model
{
    protected Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }
}
