<?php
class Seedtable {

    public $priority;
    public $requiredBy;
    public $tname;
    public $writerMethod;

    public function __construct($tableName,$priority,$writerMethod,$requiredBy=[])
    {
        $this->tname = $tableName;
        $this->priority = $priority;
        $this->requiredBy = $requiredBy;
        $this->writerMethod = $writerMethod;
        //$this->seederImplementation = 
    }
}
