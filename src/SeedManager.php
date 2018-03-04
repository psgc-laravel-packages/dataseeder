<?php
namespace PsgcLaravelPackages\DataSeeder;

class SeedManager {

    protected $_seedArray = null;
    protected $_seedRecords = null;
    protected $_seedData = null;
    protected $_stats = [];

    protected $_SEED_TABLE = null;


    protected $_seedsIn = null;
    protected $_queuedSeeds = null;
    protected $_truncate = 0;
    protected $_debug = 0;

    protected $_timestamp = null;

    protected static  $_seedPath = null;

    public function __construct($seedsIn,$seedSubpath,$truncate,$debug)
    {
        $this->_timestamp = date('Y-m-d H:i:s');
        $this->_seedsIn = $seedsIn;
        $this->_queuedSeeds = self::orderByPriority($seedsIn);
        $this->_truncate = $truncate;
        $this->_debug = $debug;
        //dd('SeedMangaer::__construct()',$seedsIn,$this->_queuedSeeds);

        config(['is_seeder' => true]);
    }

    public function seed()
    {
        //\Illuminate\Support\Facades\Event::fake();
        //$this->info( 'DB Connection: '.env('DB_CONNECTION','mysql') );

        // %TODO: strip leading/trailing slashes on $seedSubPath
        if ( empty($seedSubPath) ) {
            throw new \Exception('Option --seedpath= must be provided with a subfolder to read under database/seedfiles/');
        }
        self::$_seedPath = base_path() . "/database/seedfiles/" . $seedSubPath . "/";
        if( !\File::exists(self::$_seedPath) ) {
            throw new \Exception('Can no locate directory, seedPath: '.self::$_seedPath);
        }

        $seedManager = new \App\Libs\Seeders\SeedManager($this->_seedsIn,$truncate,$debug);
        $queuedSeeds = $seedManager->getQueue();

        if ($truncate) {
            // First check that server is whitelisted for truncate...
            $serverID = env('SERVER_ID', null);
            switch ($serverID) {
                case 'peter-localhost':
                case 'nl-web1-stage':
                case 'nl-utworx-dev1':
                    // These servers allow truncate
                    $this->truncate($seedManager,$queuedSeeds);
                    break;
                default: 
                    // do nothing
                    throw new \Exception('Server '.$serverID.' not whitelisted for truncate option...done');
                    //$this->info('Server '.$serverID.' not whitelisted for truncate option...skipping...');
            }
        }
        $this->_seed($queuedSeeds);

        $this->setTimestamps($seedManager,$queuedSeeds); // %FIXME: this will update all records in table, so if truncate isn't used it's technically not correct

    } // seed()




    public static function orderByPriority($seedsIn)
    {
        // 1 is highest...
        $availSeedsByPriority = [
            'roles'               => 10,
            'accounts'            => 30,
            'poles'               => 40,
            'applications'        => 50,
            'users'               => 80,
               
        ]; 

        $queuedSeeds = [];
        foreach ($seedsIn as $s) {
            if ( array_key_exists($s,$availSeedsByPriority) ) {
                $priority = $availSeedsByPriority[$s];
                if ( empty($queuedSeeds[$priority]) ) {
                    $queuedSeeds[$priority] = [];
                }
                $queuedSeeds[$priority][] = $s;
            }
        } // foreach()

        ksort($queuedSeeds);

        return $queuedSeeds;
    }

    public function getQueue()
    {
        return $this->_queuedSeeds;
    }



    public function truncateTable($seed)
    {
        switch ($seed) {
            case 'migrations':
            case 'importfiles':
            case 'importqueue':
                return; // skip, do not truncate
            case 'users':
            case 'roles':
                \DB::table('role_user')->truncate();
                break;
            default:
                // continue...
        }

        try { 
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            \DB::table($seed)->truncate();
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            //\File::cleanDirectory(storage_path('import_queue')); //erase all files in import_queue
            //$this->info('');
            //$this->info('storage/import_queue dir cleaned');
        } catch (\Exception $e) {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    } // truncateTable()

    protected function _truncate($seedManager,$queuedSeeds)
    {
        //dd('truncate',$queuedSeeds);
        // Truncates should be done all in one go, before the seed inserts
        foreach ($queuedSeeds as $p => $seeds ) {
            foreach ($seeds as $s ) {
                // Check dependiencies
                switch ($s) {
                    case 'applications':
                        $this->info('Truncating table: application_pole');
                        $seedManager->truncateTable('application_pole');
                        break;
                    case 'poles':
                        break;
                        /*
                    case 'users':
                        if ( !in_array('roles',$this->_seedsIn) ) {
                            //throw new \Exception('users seeder run with truncate option requires roles seeder');
                        }
                        break;
                    case 'roles':
                        if ( !in_array('users',$this->_seedsIn) ) {
                            throw new \Exception('roles seeder run with truncate option requires users seeder');
                        }
                        break;
                         */
                } // switch()

                $this->info('Truncating table: '.$s);
                $seedManager->truncateTable($s); // %FIXME: don't we do this in seed classes too?
            }
        }
    }

    public function setTimestamps($seedManager,$queuedSeeds)
    {
        foreach ($queuedSeeds as $p => $seeds ) {
            foreach ($seeds as $s ) {
                $seedManager->_setTimestamps($s,$this->_timestamp);
            } // foreach()
        } // foreach()
    } // setTimestamps()

    protected function _setTimestamps($seed,$timestamp)
    {
        // %FIXME: timestamp should be done in caller, and exactly the same for all tables written
        DB::table($seed)->update([
            'created_at'=>$timestamp,
            'updated_at'=>$timestamp,
        ]);
    }

    protected function _seed($queuedSeeds)
    {
        try { 
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');


            // Run the seeders...
            foreach ($queuedSeeds as $p => $seeds ) {

                // there may be multiple seeds with same priority...
                foreach ($seeds as $s ) {

                    switch ($s) {

                        case 'accounts':
                            $this->info('Agencies...');
                            $accountsSeeder = new \App\Libs\Seeders\AccountsSeeder( \File::get(self::$_seedPath.'accounts.json'), 0); // 0 => json string-formatted input
                            $accountsSeeder->writeSeeds();
                            //dd($agenciesSeeder->getStats());
                            break;
                        case 'applications':
                            $this->info('Applications...');
                            $applicationsSeeder = new \App\Libs\Seeders\ApplicationsSeeder( \File::get(self::$_seedPath.'applications.json'), 0); // 0 => json string-formatted input
                            $applicationsSeeder->writeSeeds();
                            //dd($agenciesSeeder->getStats());
                            break;
                        case 'poles':
                            $this->info('Poles...');
                            $polesSeeder = new \App\Libs\Seeders\PolesSeeder( \File::get(self::$_seedPath.'poles.json'), 0); // 0 => json string-formatted input
                            $polesSeeder->writeSeeds();
                            break;
                        case 'roles':
                            $this->info('Roles...');
                            $rolesSeeder = new \App\Libs\Seeders\RolesSeeder( \File::get(self::$_seedPath.'roles.json'), 0);
                            $rolesSeeder->writeSeeds();
                            break;
                        case 'users':
                            $this->info('Users...');
                            $usersSeeder = new \App\Libs\Seeders\UsersSeeder( \File::get(self::$_seedPath.'users.json'), 0);
                            $usersSeeder->writeSeeds();
                            //$usersSeeder->debug();
                            break;
                        } // switch()
                } // foreach()
            } // foreach()


            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        } catch (\Exception $e) {

            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            throw $e;

        }
    } // seed()


}

