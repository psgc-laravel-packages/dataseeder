<?php
namespace PsgcLaravelPackages\DataSeeder;

abstract class BaseSeeder {

    protected $_seedArray = null;
    protected $_seedRecords = null;
    protected $_seedData = null;
    protected $_stats = [];

    protected $_SEED_TABLE = null;

    public function __construct($seedData,$isRawPhpArray=1)
    {
        $this->_seedArray = [];
        $this->_seedRecords = [];
        $this->_stats = ['total'=>0,'new'=>0,'skipped'=>0,'errors'=>0];
        if ($isRawPhpArray) {
            $this->_seedData = $seedData;
        } else {
            // json-encoded
            $this->_seedData = json_decode($seedData,1);
        }
        $this->loadSeedArray(); 
    }

    /* Public API */
    abstract public        function writeSeeds($skipDupicates); // Write all seeds to DB

    // Read seeds *from* DB 
    // If any 'has many' relations need to be tacked on, override this method in child class
    //  after calling this as 'parent'
    public static function readSeeds($tableName)
    {
        // Default implemenation if table has *no* 'has many' relations
//dd("table",$this->_SEED_TABLE);
        $records = \DB::table($tableName)->get();
        $seedArray = [];
        foreach ($records as $r) {
            $seed = static::seedToArray($r); // default for most fields
            $seedArray[] = $seed;
        }
        return [$tableName=>$seedArray];
    } // readSeeds()

    public function getSeedData()
    {
        return $this->_seedData;
    }

    public function getSeedArray()
    {
        return $this->_seedArray;
    }
    public function getSeedRecords()
    {
        return $this->_seedRecords; // actual DB records
    }

    public function getStats()
    {
        return $this->_stats;
    }

    public function updateStats(&$modelObj)
    {
        ++$this->_stats['total'];
        if ( true===$modelObj->_isNew ) {
            ++$this->_stats['new'];
        } else if ( false===$modelObj->_isNew ) {
            ++$this->_stats['skipped'];
        }
    }

    // Is the key a comment?
    public function isComment($key)
    {
        $is = ( '#' == substr($key,0,1) );
        return $is;
    }

    /* Protected Methods */

    // sets _seedArray
    protected function loadSeedArray()
    {
        /*
        if ( empty($this->_seedData) ) {
            return; // skip, nothing to do...
        }
         */
        $this->_seedArray = []; // data to insert to DB
        foreach ($this->_seedData[$this->_SEED_TABLE] as $iter => $_seed) {
            $this->_seedArray[$iter] = $this->parseSeed($_seed);
        } // foreach ($seedData...) : loop through list of agencies in seed data
        return $this; // chain-able %TODO %NOTE : good interview question
    } // loadSeedArray()

    
    // Parse any special fields:
    //   '#' : indicates a comment
    //   '@' : indicates parent relation (key), or a key that needs special processing
    //   '+' : indicates child relation(s) (key)
    //   '%' : indicates a sepcial value (eg %inherit)
    abstract protected        function parseSeed($seed); // Parse and handle any special fields

    //abstract protected static function seedToArray(); // convert DB record format to 'array'

    //abstract public        function loadSeedRecords();

}
