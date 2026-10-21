<?php
/** Deliberately NOT wpdb: fixed frame API double, not genuine core provenance. */
final class Alynt_Test_Native_Lifecycle_Auth_Db {
    public $dbh;
    public function __construct() { $this->dbh = new mysqli(); }
}
