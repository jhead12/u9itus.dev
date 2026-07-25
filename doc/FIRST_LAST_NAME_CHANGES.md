# First Name / Last Name Implementation

## Overview

Split the single "Full name" field into separate "First Name" and "Last Name" fields across all user registration and profile forms for better user tracking and data organization.

## Changes Made

### 1. Database Migration

- **File**: `database/migrations/2026_03_04_060911_make_name_nullable_on_users_table.php`
- Made the `name` column nullable to support the transition to `first_name` and `last_name`
- Note: `first_name` and `last_name` columns were already added in a previous migration

### 2. User Model Updates

- **File**: `app/Models/User.php`
- Added `getNameAttribute()` accessor to combine `first_name` and `last_name` for backward compatibility
- Added `setNameAttribute()` mutator to split full name into parts when `name` is set directly
- Both `first_name` and `last_name` were already in the fillable array

### 3. Registration Forms Updated

#### Voter Registration

- **Form**: `resources/views/standalone/auth/register-voter.blade.php`
- **Controller**: `app/Http/Controllers/Standalone/AuthController.php` - `registerVoter()` method
- Split name field into first_name and last_name fields side-by-side
- Updated validation and user creation logic

#### Politician Registration

- **Form**: `resources/views/standalone/auth/register-politician.blade.php`
- **Controller**: `app/Http/Controllers/Standalone/AuthController.php` - `registerPolitician()` method
- Split name field into first_name and last_name fields side-by-side
- Removed old name splitting logic that was using `explode()`

#### General Registration

- **Form**: `resources/views/auth/register.blade.php`
- **Controller**: `app/Http/Controllers/Auth/RegisteredUserController.php` - `store()` method
- Split name field into first_name and last_name fields side-by-side
- Updated validation rules

#### Standalone Registration (Alternative)

- **Form**: `resources/views/standalone/auth/register.blade.php`
- Split name field into first_name and last_name fields side-by-side

### 4. Profile Forms Updated

#### Standard Profile Form

- **File**: `resources/views/profile/partials/update-profile-information-form.blade.php`
- **Validation**: `app/Http/Requests/ProfileUpdateRequest.php`
- Split name field into first_name and last_name in a grid layout
- Updated validation rules to use `first_name` and `last_name`

#### Admin Profile Form

- **Form**: `resources/views/standalone/admin/profile.blade.php`
- **Controller**: `app/Http/Controllers/Standalone/AdminController.php` - `updateProfile()` method
- Split name field into first_name and last_name in a grid layout
- Updated validation and save logic

### 5. Seeders Updated

- **File**: `database/seeders/AdminUserSeeder.php`
- Removed redundant `name` field assignment (now relies on accessor)
- Kept `first_name` and `last_name` which were already present

### 6. Factory Updates

- **File**: `database/factories/UserFactory.php`
- No changes needed - already generates both `first_name`, `last_name`, and `name` fields

## Benefits

1. **Better Data Organization**: Names are now stored in standardized first/last fields
2. **Improved Sorting**: Easy to sort users by last name then first name
3. **Professional Best Practice**: Industry standard to separate name components
4. **Backward Compatibility**: The `name` accessor/mutator ensures old code still works
5. **Better UX**: Clearer fields help users enter their names correctly

## Testing Recommendations

1. Test voter registration with various name combinations
2. Test politician registration with various name combinations
3. Test profile updates for all user types
4. Verify that existing users with only `name` field still display correctly (accessor should handle this)
5. Test that seeders create admin users correctly
6. Run feature tests if available

## Migration Instructions

```bash
# Run the migration
php artisan migrate

# If needed, populate first_name/last_name from existing name column:
# php artisan tinker
# User::whereNotNull('name')->whereNull('first_name')->chunk(100, function($users) {
#     foreach($users as $user) {
#         $parts = explode(' ', trim($user->name), 2);
#         $user->first_name = $parts[0] ?? '';
#         $user->last_name = $parts[1] ?? '';
#         $user->save();
#     }
# });
```

## Notes

- The `name` column is kept for backward compatibility and will be auto-populated via the mutator
- The accessor ensures that `$user->name` still works throughout the codebase
- All email templates and views that reference `$user->name` will continue to work
