<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW eav_timeseries_latest AS
SELECT id, entity_id, attribute_id, value, observed_at
FROM (
  SELECT t.*,
         ROW_NUMBER() OVER (PARTITION BY entity_id, attribute_id ORDER BY observed_at DESC, id DESC) AS rn
  FROM eav_timeseries t
) x
WHERE rn = 1;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW entity_attribute_resolved AS
SELECT v.entity_id,
       v.attribute_id,
       COALESCE(NULLIF(v.value_override,''), v.value_current) AS resolved_with_override,
       v.value_current                                    AS resolved_current_only,
       'versioned' AS src
FROM eav_versioned v
UNION ALL
SELECT i.entity_id,
       i.attribute_id,
       i.value AS resolved_with_override,
       i.value AS resolved_current_only,
       'input' AS src
FROM eav_input i
UNION ALL
SELECT tl.entity_id,
       tl.attribute_id,
       tl.value AS resolved_with_override,
       tl.value AS resolved_current_only,
       'timeseries' AS src
FROM eav_timeseries_latest tl;
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW entity_attr_json AS
SELECT
  e.id AS entity_id,
  JSON_OBJECTAGG(a.name, ear.resolved_with_override) AS attrs_with_override,
  JSON_OBJECTAGG(a.name, ear.resolved_current_only)  AS attrs_current
FROM entities e
JOIN entity_attribute_resolved ear ON ear.entity_id = e.id
JOIN attributes a ON a.id = ear.attribute_id
GROUP BY e.id;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS entity_attr_json');
        DB::statement('DROP VIEW IF EXISTS entity_attribute_resolved');
        DB::statement('DROP VIEW IF EXISTS eav_timeseries_latest');
    }
};
