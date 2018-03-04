# Data Seeder

In the app:

$ php artisan make:command SeedDatabase

In app/Console/Kernel.php:

    class Kernel extends ConsoleKernel
    {
        protected $commands = [
            ...
            \App\Console\Commands\SeedDatabase::class, // <== add this
            ...
        ];

---

$seedconfigs = [
    'widgets'=> [
        'astate'=>['resolved_by'=>'\App\Models\Enum\Application\AstateEnum::findKeyBySlug'],
        'fielder'=>['belongs_to'=>['table'=>'users','keyed_by'=>'username','fkid'=>'fielder_id']],
        'account'=>['belongs_to'=>['table'=>'accounts','keyed_by'=>'accountnumber','fkid'=>'account_id']],
        // keyed_by is how we lookup the record in the related table
        // fkid is how we store the record as FKID in our table
    ],
];


{
    "widgets": [
        {
            "@pole_slugs": ["kdm-22725","kdm-22726","kdm-22727","kdm-22728"], // %FIXME: many2many we do in their own seed file !
            "@account": "55555-00001",
            "*astate": "init",
            "description": "test 1",
            "custom_appid": "AA-11-55-DX-3",
            "number_of_poles": 5,
            "contact_name": "John Smith",
            "contact_phone": "555-555-5555",
            "contact_email": "johns@example.org",
            "company_name": "Acme Inc",
            "company_address": "2 State St",
            "company_city": "Chicago",
            "@company_usstate_code": "IL",
            "@service_category": "telcom",
            "company_zipcode": "53243",
            "electric_power_requirements": "TBD"
        },

        {
            "@account": "55555-00005",
            "*astate": "assigned",
            "@fielder": "frank.renner",
            "custom_appid": "AA-11-55-YY-7",
            "description": "test 8"
        },

{
    "accounts": [
        {
            "accountnumber": "55555-00001"
        },
