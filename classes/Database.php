<?php
/**
 * Database Class Wrapper
 * Provides backward compatibility by wrapping the Database class from api/config/database.php
 */

require_once __DIR__ . '/../api/config/database.php';

// The Database class is already defined in api/config/database.php
// This file exists for backward compatibility with code that expects classes/Database.php

?>
