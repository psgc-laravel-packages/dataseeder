<?php
namespace PsgcLaravelPackages\DataSeeder;

class SeedManager {

    protected $_seedArray = null;
    protected $_seedRecords = null;
    protected $_seedData = null;
    protected $_stats = [];

    protected $_SEED_TABLE = null;

    protected $_isDebug = 0;

    protected $_timestamp = null;
    protected $_configs = [];

    const TRUNCATE_BLACKLIST = ['migrations', 'importfiles', 'importqueue', ];

    public function __construct($configs, $isDebug)
    {
        $this->_configs = $configs;
        $this->_isDebug = $isDebug;
        $this->_timestamp = date('Y-m-d H:i:s');
        //dd('SeedMangaer::__construct()',$seedsIn,$this->_queuedSeeds);

        config(['is_seeder' => true]);
    }

    public function seed($seeds,$srcFilepath,$doTruncate=false)
    {
        //\Illuminate\Support\Facades\Event::fake();
        //$this->info( 'DB Connection: '.env('DB_CONNECTION','mysql') );

        $this->checkFilepath($srcFilepath);

        $queuedSeeds = $this->keysortByPriority($seeds);

        if ( $doTruncate && $this->canTruncate() ) {
            $this->truncate($seedManager,$queuedSeeds);
        }

        $this->_seed($queuedSeeds);

        $this->setTimestamps($seedManager,$queuedSeeds); // %FIXME: this will update all records in table, so if truncate isn't used it's technically not correct

    } // seed()


    protected function _seed($queuedSeeds)
    {
        try { 
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Run the seeders...
            foreach ($queuedSeeds as $p => $seedtables ) {

                // there may be multiple seeds with same priority...
                foreach ($seedtables as $st ) {

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
                            //$usersSeeder->isDebug();
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

    protected canTruncate()
    {
        // check that server is whitelisted for truncate...
        $can = false; // default
        if ( array_key_exists('whitelisted', $this->_configs) ) {
            $serverID = env('SERVER_ID', null);
            $can = isset($serverID) && in_array($serverID,$this->_configs['whitelisted']);
        }
        return $can;
    }

    protected checkFilepath($filepath)
    {
        // %TODO: strip leading/trailing slashes on $filepath
        if ( empty($filepath) ) {
            throw new \Exception('Option --filepath= must be provided with a valid filepath from which to  read seedfiles');
        }
        if( !\File::exists($filepath) ) {
            throw new \Exception('Can no locate directory, filepath: '.$filepath);
        }
    }

    // Input:
    //   [
    //      'roles'     => {Seedtable} w/ priority 10
    //      'widgets'   => {Seedtable} w/ priority 30
    //      'gadgets'   => {Seedtable} w/ priority 30
    //      'users'     => {Seedtable} w/ priority 80
    //  ]
    //
    // Output:
    //   [
    //      10 => [{Seedtable}] -> ['roles'],
    //      30 => [{Seedtable}] -> ['widgets','gadgets'],
    //      80 => [{Seedtable}] -> ['users'],
    //  ]
    // 1 is highest priority
    //
    protected function keysortByPriority($seeds)
    {
        $queue = [];
        foreach ($seeds as Seedtable $st) { // %FIXME: check type
            if ( empty($queue[$st->priority]) ) {
                $queue[$st->priority] = []; // init
            }
            $queue[$st->priority][] = $st;
        } // foreach()

        ksort($queue);
        return $queue;
    }



    protected function truncate($queuedSeeds)
    {
        // Truncates should be done all in one go, before the seed inserts
        // %FIXME: does *NOT* support recurision. Only 1 level of dependendcies
        foreach ($queuedSeeds as $p => $seeds ) {
            foreach ($seeds as Seedtable $st ) {
                if ( in_array($st->tname,self::TRUNCATE_BLACKLIST) ) {
                    return; // skip, do not truncate
                }
                //$this->info('Truncating table: '.$s);
                try { 
                    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                    // Check dependiencies
                    foreach ( $st->requiredBy as $dtname ) {
                        //$this->info('Truncating depdendent table: '.$dt);
                        DB::table($dtname)->truncate(); // dt: dependent table
                    }
                    DB::table($st->tname)->truncate();
                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                    //\File::cleanDirectory(storage_path('import_queue')); //erase all files in import_queue
                    //$this->info('');
                    //$this->info('storage/import_queue dir cleaned');
                } catch (\Exception $e) {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                }
            } // foeach($seeds)
        } // foeach($queuedSeeds))
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


}

