<?php
namespace PsgcLaravelPackages\DataSeeder;

abstract class BaseSeeder {

    protected $_seedArray = null;
    protected $_seedRecords = null;
    protected $_seedData = null;
    protected $_stats = [];

    protected $_SEED_TABLE = null;

    protected $_seedconfigs = [];

    public function __construct($seedconfigs, $seedData)
    {
        $this->_seedconfigs = $seedconfigs;

        $this->_seedRecords = [];
        $this->_stats = ['total'=>0,'new'=>0,'skipped'=>0,'errors'=>0];
        $this->_seedData = json_decode($seedData,1); // json-encoded

        $this->_seedArray = []; // data to insert to DB
        foreach ($this->_seedData[$this->_SEED_TABLE] as $iter => $_seed) {
            $this->_seedArray[$iter] = $this->parseSeed($_seed);
        } 

    }


    //abstract protected  function parseSeed($seed); // Parse and handle any special fields

    // Parse any special fields:
    //   '#' : indicates a comment
    //   '@' : indicates 'belongsTo' that does not use raw FK id (for instance the relation's slug instead of PKID)
    //   '...' : indicates parent relation (key), or a key that needs special processing
    //   '+' : indicates child relation(s) (key)
    //   '%' : indicates a sepcial value (eg %inherit)
    // Parse and handle any special fields
// HERE MONDAY %TODO
    protected function parseSeed($seed)
    {
        foreach ($seed as $key => $value) {


            $prefix = substr($key,0,1);

            switch ($prefix) {

                case '#': // comment
                    $configKey = substr($key, 1); // remove prefix to get raw key
                    unset($seed[$key]);
                    continue; // skip comments

                case '@': // relation alias
                    $configKey = substr($key, 1); // remove prefix to get raw key
                    if ( array_key_exists($configKey, $this->_seedconfigs) ) {
                        $rule = $this->_seedconfigs[$configKey];
                        if ( array_key_exists('belongs_to', $rule) ) {
                            $belongsTo = $rule['belongs_to'];
                            //$record = \App\Models\User::where('username',$seed['@fielder'])->firstOrFail();
                            $record = DB::table($belongsTo['table'])->where($belongsTo['keyed_by'], $seed[$key])->firstOrFail();
                            $seed[$belongsTo['fkid']] = $record->id;
                        }
                    }
                    continue;

                case '*': // lookup via method
                    $configKey = substr($key, 1); // remove prefix to get raw key
                    if ( array_key_exists($configKey, $this->_seedconfigs) ) {
                        $rule = $this->_seedconfigs[$configKey];
                        if ( array_key_exists('resolved_by', $rule) ) {
                            //'astate'=>['resolved_by'=>'\App\Models\Enum\Application\AstateEnum::findKeyBySlug'],
                            $fResolvedBy = $rule['resolved_by'];
                            $seed[$configKey] = $fResolvedBy($value); // function call (%FIXME: use map?)
                            //$seed['service_category'] = AAservicecategoryEnum::findKeyBySlug($value);
                        }
                    }
                    continue;

                case '%':
                    $configKey = substr($key, 1); // remove prefix to get raw key
                    // TBD
                    continue;

                case '+':
                    $configKey = substr($key, 1); // remove prefix to get raw key
                    // TBD
                    continue;

                default:
                    $configKey = $key; // no prefix
                    $seed[$configKey] = $value; // direct assignment

            } // switch()

        } // foreach ($seed)

        return $seed;


    } // parseSeed()

    public function writeSeeds($skipDuplicates=1)
    {
        foreach ($this->_seedArray as $iter=> $seed) {
            $pkid = DB::table($this->_SEED_TABLE)->insertGetId($this->_seedArray);
            $record = DB::table($this->_SEED_TABLE)->find($pkid);
            $this->_seedRecords[] = $record;
            //$this->updateStats($record);
        }

    } // writeSeeds()

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


    /* Protected Methods */

    

    //abstract protected static function seedToArray(); // convert DB record format to 'array'

    //abstract public        function loadSeedRecords();

}
