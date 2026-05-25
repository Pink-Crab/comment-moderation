# migrations/

Database schema migrations live here. The scaffold ships **core Perique only**,
so the migrations module is NOT enabled out of the box. The folder, its PSR-4
mapping in [composer.json](../composer.json) (`"##NAMESPACE##\\": ["src/", "migrations/"]`),
and its `.gitkeep` are committed so it's a one-step add when you need it.

## Enabling the migration module

The Perique migration module **requires** the plugin lifecycle module too — it
hooks into activation/deactivation/uninstall to create/seed/drop tables.

1. Install both packages:

   ```bash
   composer require pinkcrab/perique-plugin-lifecycle pinkcrab/perique-migration
   ```

2. Wire both modules in [perique-bootstrap.php](../perique-bootstrap.php) (order
   matters — `Plugin_Life_Cycle` first):

   ```php
   use PinkCrab\Plugin_Lifecycle\Module\Plugin_Life_Cycle;
   use PinkCrab\Perique\Migration\Module\Perique_Migrations;
   use ##NAMESPACE##\Create_Things_Table; // your migration class

   ( new App_Factory( ##CONSTANT_PREFIX##PATH ) )
       ->default_setup()
       // ...
       ->module(
           Plugin_Life_Cycle::class,
           fn( Plugin_Life_Cycle $m ): Plugin_Life_Cycle => $m
               ->plugin_base_file( ##CONSTANT_PREFIX##PATH . '##PLUGIN_SLUG##.php' )
       )
       ->module(
           Perique_Migrations::class,
           fn( Perique_Migrations $m ): Perique_Migrations => $m
               ->set_migration_log_key( '##FUNCTION_PREFIX##migrations' )
               ->add_migration( Create_Things_Table::class )
       )
       ->boot();
   ```

3. Write a migration class in this directory (it's PSR-4 mapped — same as
   `src/`). Extends `PinkCrab\Perique\Migration\Migration`:

   ```php
   <?php
   namespace ##NAMESPACE##;

   use PinkCrab\Perique\Application\App_Config;
   use PinkCrab\Perique\Migration\Migration;
   use PinkCrab\Table_Builder\Schema;

   final class Create_Things_Table extends Migration {

       public function __construct( private App_Config $app_config ) {
           parent::__construct();
       }

       public function table_name(): string {
           return $this->app_config->db_tables( 'things' );
       }

       public function schema( Schema $schema ): void {
           $schema->column( 'id' )->unsigned_int( 11 )->auto_increment();
           $schema->column( 'name' )->varchar( 255 );
           $schema->column( 'created_at' )->datetime();
           $schema->index( 'id' )->primary();
       }

       public function seed( array $seeds ): array {
           return $seeds;
       }

       public function drop_on_uninstall(): bool {
           return true;
       }
   }
   ```

4. Register the alias for `table_name()` in [config/settings.php](../config/settings.php)
   under `db_tables`:

   ```php
   'db_tables' => array(
       'things' => $GLOBALS['wpdb']->prefix . '##FUNCTION_PREFIX##things',
   ),
   ```

See the Perique docs (`perique-modules.md`) for `up()` / `down()` lifecycle
hooks, seeding, and the full Schema API.
