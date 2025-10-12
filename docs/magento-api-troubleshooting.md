# Magento API Troubleshooting

## Error: "The consumer isn't authorized to access %resources"

### Symptoms
- API calls return 401 or 403 errors
- Error message: `The consumer isn't authorized to access Magento_Catalog::attributes_attributes`
- Even though integration shows "All" permissions

### Common Causes
1. **Stale cache** - Magento hasn't refreshed permission cache
2. **Token not regenerated** - Permissions changed but token wasn't reissued
3. **Specific resource not granted** - "All" checkbox isn't actually checking all sub-resources
4. **Wrong integration type** - Using admin user token vs integration token

---

## Fix Steps

### Step 1: Clear Magento Cache
```bash
docker exec m2_app bin/magento cache:flush
docker exec m2_app bin/magento cache:clean
```

### Step 2: Verify Integration Exists and Has Permissions

**Option A: Via Magento Admin UI**

1. Navigate to: **System → Integrations**
2. Find your SPIM integration (or create one if missing)
3. Click **Edit**
4. Under **API** tab, ensure **"Resource Access"** is set to **"All"**
5. Click **Save**
6. Click **Activate** (or **Reauthorize** if already active)
7. **Copy the new Access Token** from the popup
8. Update SPIM `.env`:
   ```env
   MAGENTO_ACCESS_TOKEN=<new_token_here>
   ```

**Option B: Via MySQL (if UI doesn't work)**

Check current integration permissions:
```bash
docker exec m2_db mysql -u magento -pmagento magento -e \
  "SELECT * FROM oauth_consumer WHERE name LIKE '%SPIM%' OR name LIKE '%integration%';"
```

### Step 3: Check Specific Resource Permissions

If "All" doesn't work, manually check these critical resources in the integration:

**Required for Product Sync:**
- ✅ `Magento_Catalog::catalog`
- ✅ `Magento_Catalog::products`
- ✅ `Magento_Catalog::categories`
- ✅ `Magento_Catalog::attributes_attributes` ⚠️ **This one for attribute options!**

**In Magento Admin:**
1. System → Integrations → Edit your integration
2. API tab → Expand **Catalog** section
3. Manually check:
   - Catalog
   - Products  
   - Product Attributes ⬅️ **Make sure this is checked!**
   - Categories
4. Save and **Reauthorize**

### Step 4: Test the Token

Test with curl directly:
```bash
# Replace YOUR_TOKEN and m2.ftn.test with your values
curl -X GET "https://m2.ftn.test/rest/V1/products/attributes/short_description/options" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected responses:**
- ✅ **200 OK** with JSON array of options → Success!
- ❌ **401** → Token is invalid or expired
- ❌ **403** → Token lacks permissions (keep troubleshooting)

### Step 5: Verify Token in Database

```bash
docker exec m2_db mysql -u magento -pmagento magento -e \
  "SELECT t.token, t.consumer_id, t.created_at, c.name 
   FROM oauth_token t 
   JOIN oauth_consumer c ON t.consumer_id = c.entity_id 
   WHERE t.type = 'access' 
   ORDER BY t.created_at DESC 
   LIMIT 5;"
```

Make sure:
1. Your token exists in the database
2. It's associated with the correct integration name
3. The `created_at` date is recent (if you just regenerated it)

---

## Creating a New Integration (Fresh Start)

If all else fails, create a brand new integration:

### Via Magento Admin:

1. **System → Integrations → Add New Integration**
2. Fill in:
   - **Name**: `SPIM Product Sync`
   - **Email**: your email
   - **Current Password**: your admin password
3. **API** tab:
   - **Resource Access**: Select **"All"**
4. Click **Save**
5. Click **Activate**
6. **Copy all credentials** (especially the Access Token)
7. Update SPIM `.env`:
   ```env
   MAGENTO_BASE_URL=https://m2.ftn.test
   MAGENTO_ACCESS_TOKEN=<paste_access_token_here>
   ```

### Via CLI (Alternative Method):

```bash
# Create integration
docker exec m2_app bin/magento integration:create \
  --name="SPIM Product Sync" \
  --email="admin@example.com" \
  --endpoint="https://spim.test/api/callback" \
  --identity-link-url="https://spim.test/api/identity"

# Note: You'll need to activate it in the admin UI to get the token
```

---

## Common Mistakes

### ❌ Mistake 1: Using Admin User Token Instead of Integration Token
**Problem**: Personal admin tokens have different permissions than integration tokens

**Fix**: Use an Integration token, not a user token

### ❌ Mistake 2: Not Regenerating Token After Permission Changes
**Problem**: Permissions are cached in the token itself

**Fix**: Always click "Reauthorize" and get a new token after changing permissions

### ❌ Mistake 3: Checking "All" But Sub-Resources Aren't Actually Selected
**Problem**: Magento UI bug sometimes doesn't properly cascade the "All" checkbox

**Fix**: Manually expand each section and verify sub-resources are checked

### ❌ Mistake 4: Cache Not Cleared
**Problem**: Magento aggressively caches ACL permissions

**Fix**: Always flush cache after changing permissions:
```bash
docker exec m2_app bin/magento cache:flush
```

---

## Testing Integration Works

After fixing, test the integration with this command:

```bash
# From SPIM
docker exec spim_app php artisan tinker

# Then in tinker:
$client = app(\App\Services\MagentoApiClient::class);
$products = $client->getProducts();
dd($products);
```

Or test attribute options directly:
```bash
docker exec spim_app php artisan tinker

# Then in tinker:
$client = app(\App\Services\MagentoApiClient::class);
$options = $client->getAttributeOptions('short_description');
dd($options);
```

---

## Still Not Working?

### Check Magento Logs
```bash
docker exec m2_app tail -f /var/www/html/var/log/system.log
docker exec m2_app tail -f /var/www/html/var/log/exception.log
```

### Check Nginx/Apache Logs
```bash
docker logs m2_nginx
```

### Verify Magento API is Working at All
Test a public endpoint that doesn't require auth:
```bash
curl -X GET "https://m2.ftn.test/rest/V1/store/storeViews"
```

If this fails, the problem is with Magento itself, not your integration.

---

## Quick Reference: Required Magento Resources for SPIM Sync

| SPIM Feature | Required Magento Resource |
|--------------|---------------------------|
| Product sync | `Magento_Catalog::products` |
| Attribute option sync | `Magento_Catalog::attributes_attributes` ⭐ |
| Category sync | `Magento_Catalog::categories` |
| Create products | `Magento_Catalog::products` (save) |
| Upload images | `Magento_Catalog::products` (save) |
| Update products | `Magento_Catalog::products` (save) |

⭐ = The one causing your current error!

