# Magento Sync Testing Plan

## Test Organization

### Unit Tests (`tests/Unit/`)
Tests for individual classes in isolation with mocked dependencies

### Feature Tests (`tests/Feature/`)
Integration tests that test the full flow with database interactions

---

## 1. Unit Tests

### `MagentoApiClientTest.php`
Test the API client in isolation with mocked HTTP responses

**Test Cases:**
- ✅ `test_can_get_products_list` - Fetch products with filters
- ✅ `test_can_get_single_product` - Fetch by SKU
- ✅ `test_can_create_product` - Create new product
- ✅ `test_can_update_product` - Update existing product
- ✅ `test_can_get_attribute_options` - Fetch select/multiselect options
- ✅ `test_can_create_attribute_option` - Add new option
- ✅ `test_can_upload_image` - Upload media file
- ✅ `test_handles_api_errors_gracefully` - 4xx/5xx responses
- ✅ `test_retries_on_failure` - Automatic retry logic
- ✅ `test_respects_timeout` - Request timeout handling

---

### `AttributeOptionSyncTest.php`
Test attribute option sync logic in isolation

**Test Cases:**
- ✅ `test_syncs_options_from_magento_to_spim` - Pull missing options
- ✅ `test_replaces_spim_options_when_magento_differs` - Magento as source of truth
- ✅ `test_skips_attributes_that_are_already_synced` - No-op when identical
- ✅ `test_only_syncs_select_and_multiselect_attributes` - Ignores text/etc
- ✅ `test_only_syncs_attributes_with_is_synced_true` - Respects flag
- ✅ `test_logs_sync_results_to_database` - Creates SyncResult records
- ✅ `test_handles_magento_api_errors` - Graceful error handling
- ✅ `test_updates_stats_correctly` - Tracks created/updated/skipped/errors

---

### `ProductSyncTest.php`
Test product sync logic in isolation

**Test Cases:**

**Pull (Magento → SPIM):**
- ✅ `test_imports_new_products_from_magento` - Create Entity + attributes
- ✅ `test_updates_existing_products_with_input_attributes` - Only input attrs
- ✅ `test_sets_all_three_value_fields_on_initial_import` - value_current/approved/live
- ✅ `test_respects_value_override_when_present` - Doesn't overwrite overrides
- ✅ `test_handles_missing_attributes_gracefully` - Skip unmapped attributes

**Push (SPIM → Magento):**
- ✅ `test_creates_products_in_magento_when_missing` - New product creation
- ✅ `test_creates_products_as_disabled` - status=2 for new products
- ✅ `test_updates_existing_products_with_versioned_attributes` - Only versioned
- ✅ `test_only_syncs_when_value_approved_differs_from_value_live` - Optimization
- ✅ `test_updates_value_live_after_successful_push` - Mark as synced
- ✅ `test_uses_value_override_when_present` - Override takes precedence
- ✅ `test_skips_attributes_with_is_synced_false` - Respects flag

**Transaction & Error Handling:**
- ✅ `test_uses_per_product_transactions` - Isolation
- ✅ `test_continues_after_individual_product_failure` - Resilience
- ✅ `test_logs_sync_results_to_database` - Creates SyncResult records
- ✅ `test_tracks_stats_correctly` - created/updated/skipped/errors

**Single Product Mode:**
- ✅ `test_syncs_single_product_by_sku` - --sku flag

---

## 2. Feature Tests (Integration)

### `MagentoSyncJobsTest.php`
Test the queue jobs with database and full service stack

**Test Cases:**

**SyncAttributeOptions Job:**
- ✅ `test_sync_attribute_options_job_creates_sync_run` - Database tracking
- ✅ `test_sync_attribute_options_job_logs_results` - SyncResult records
- ✅ `test_sync_attribute_options_job_tracks_user` - User attribution
- ✅ `test_sync_attribute_options_job_can_be_queued` - Queue::fake()

**SyncAllProducts Job:**
- ✅ `test_sync_all_products_job_creates_sync_run` - Database tracking
- ✅ `test_sync_all_products_job_syncs_all_entities` - Full sync
- ✅ `test_sync_all_products_job_logs_results` - SyncResult records
- ✅ `test_sync_all_products_job_tracks_user` - User attribution
- ✅ `test_sync_all_products_job_can_be_queued` - Queue::fake()

**SyncSingleProduct Job:**
- ✅ `test_sync_single_product_job_creates_sync_run` - Database tracking
- ✅ `test_sync_single_product_job_syncs_one_entity` - Single SKU
- ✅ `test_sync_single_product_job_logs_results` - SyncResult records
- ✅ `test_sync_single_product_job_tracks_user` - User attribution
- ✅ `test_sync_single_product_job_can_be_queued` - Queue::fake()

---

### `MagentoSyncEndToEndTest.php`
Full end-to-end scenarios with real workflow

**Test Cases:**

**Full Sync Workflow:**
- ✅ `test_full_sync_workflow_with_new_product` - Options → Product → Verify
- ✅ `test_full_sync_workflow_with_existing_product` - Update scenario
- ✅ `test_bidirectional_sync` - Pull input, push versioned
- ✅ `test_sync_respects_attribute_type_rules` - versioned vs input vs timeseries

**Error Scenarios:**
- ✅ `test_handles_magento_api_down` - Graceful failure
- ✅ `test_handles_invalid_attribute_mapping` - Validation errors
- ✅ `test_partial_sync_with_some_failures` - Continues on errors
- ✅ `test_sync_run_marked_as_failed_when_errors_occur` - Status tracking

**Database Logging:**
- ✅ `test_sync_run_records_created_with_correct_stats` - Total/success/fail counts
- ✅ `test_sync_results_contain_detailed_error_messages` - Error details
- ✅ `test_sync_results_track_before_and_after_values` - Details field

---

### `MagentoSyncCommandTest.php`
Test the Artisan commands

**Test Cases:**
- ✅ `test_sync_magento_options_command` - sync:magento:options {entityType}
- ✅ `test_sync_magento_command_without_sku` - sync:magento {entityType}
- ✅ `test_sync_magento_command_with_sku` - sync:magento {entityType} --sku=SKU
- ✅ `test_commands_queue_jobs` - Verify jobs dispatched
- ✅ `test_commands_fail_with_invalid_entity_type` - Error handling

---

### `MagentoSyncCleanupTest.php`
Test the cleanup command

**Test Cases:**
- ✅ `test_cleanup_deletes_old_sync_results` - >30 days
- ✅ `test_cleanup_preserves_recent_sync_results` - <30 days
- ✅ `test_cleanup_respects_custom_days_option` - --days=X
- ✅ `test_cleanup_cascades_to_sync_results` - Deletes related records

---

## 3. Filament UI Tests (Optional, but recommended)

### `MagentoSyncFilamentTest.php`
Test Filament actions and pages

**Test Cases:**

**Attribute Edit Page:**
- ✅ `test_test_mapping_action_visible_when_is_synced` - Button visibility
- ✅ `test_test_mapping_action_calls_magento_api` - Functionality
- ✅ `test_sync_options_action_visible_for_select_attributes` - Button visibility
- ✅ `test_sync_options_action_queues_job` - Dispatches SyncAttributeOptions

**Entity Edit Page:**
- ✅ `test_sync_to_magento_action_visible` - Button visibility
- ✅ `test_sync_to_magento_action_queues_job` - Dispatches SyncSingleProduct

**Magento Sync Page:**
- ✅ `test_sync_page_displays_sync_runs` - Table rendering
- ✅ `test_sync_page_displays_stats_widget` - Stats correct
- ✅ `test_sync_options_action_dispatches_job` - Header action
- ✅ `test_sync_all_products_action_dispatches_job` - Header action
- ✅ `test_sync_by_sku_action_dispatches_jobs` - Multiple SKUs
- ✅ `test_view_errors_modal_shows_error_details` - Table row action
- ✅ `test_view_details_modal_shows_sync_info` - Table row action

---

## Test Data Factories

We'll need factories for:
- `EntityType` - Already exists
- `Entity` - Already exists
- `Attribute` - Already exists
- `SyncRun` - **NEW**
- `SyncResult` - **NEW**

We'll also need test seeders for common scenarios.

---

## Mocking Strategy

- **HTTP Responses**: Use `Http::fake()` for Magento API
- **Queue**: Use `Queue::fake()` for job testing
- **Notifications**: Use `Notification::fake()` for UI tests
- **Database**: Use `RefreshDatabase` trait
- **Time**: Use `Carbon::setTestNow()` for time-dependent tests

---

## Test Coverage Goals

- **Unit Tests**: 100% coverage of service methods
- **Feature Tests**: All major workflows covered
- **Edge Cases**: Error handling, retries, timeouts
- **Database**: Verify all logging and tracking
- **Performance**: Test with large datasets (optional)

---

## Test Execution

Run tests with:
```bash
# All sync tests
docker exec spim_app php artisan test --filter Magento

# Specific test file
docker exec spim_app php artisan test tests/Unit/MagentoApiClientTest.php

# With coverage
docker exec spim_app php artisan test --coverage
```

---

## Next Steps

1. Create test factories for `SyncRun` and `SyncResult`
2. Implement unit tests first (fastest feedback)
3. Implement feature tests (integration)
4. Implement UI tests (if desired)
5. Run tests and fix any issues found
6. Add CI/CD integration (optional)

