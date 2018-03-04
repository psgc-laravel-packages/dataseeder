<?php
class Seedtable {

    public $priority;
    public $requiredBy;
    public $tname;

    public function __construct($tableName,$priority,$requiredBy=[])
    {
        $this->tname = $tableName;
        $this->priority = $priority;
        $this->requiredBy = $requiredBy;
    }
}
