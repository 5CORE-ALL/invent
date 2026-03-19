# Role & Responsibility (R&R) Data Management Guide

## Method 1: Using the UI (Recommended)

1. Go to **Task Manager** page
2. Select a user from the dropdown at the top
3. Click on the **R&R** tab
4. Click the **"Edit R&R"** button
5. Fill in the form:
   - **Role**: Single line (e.g., "Senior Developer")
   - **Responsibilities**: Comma-separated list (e.g., "Code reviews, Architecture design, Team mentoring")
   - **Goals**: Comma-separated list (e.g., "Improve code quality, Reduce technical debt")
6. Click **"Save R&R"**

## Method 2: Using Laravel Tinker (Quick Method)

### Step 1: Open Tinker
```bash
php artisan tinker
```

### Step 2: Add R&R Data for a User

#### Example 1: Create R&R for a user by email
```php
$user = \App\Models\User::where('email', 'user@example.com')->first();
\App\Models\UserRR::updateOrCreate(
    ['user_id' => $user->id],
    [
        'role' => 'Senior Developer',
        'responsibilities' => 'Code reviews, Architecture design, Team mentoring, Code optimization',
        'goals' => 'Improve code quality, Reduce technical debt, Mentor junior developers, Increase test coverage'
    ]
);
```

#### Example 2: Create R&R for a user by name
```php
$user = \App\Models\User::where('name', 'John Doe')->first();
\App\Models\UserRR::create([
    'user_id' => $user->id,
    'role' => 'Project Manager',
    'responsibilities' => 'Project planning, Team coordination, Client communication, Resource allocation',
    'goals' => 'Complete projects on time, Improve team productivity, Enhance client satisfaction'
]);
```

#### Example 3: Update existing R&R
```php
$user = \App\Models\User::where('email', 'user@example.com')->first();
$userRR = $user->userRR;
if ($userRR) {
    $userRR->update([
        'role' => 'Lead Developer',
        'responsibilities' => 'Technical leadership, Code reviews, Architecture decisions',
        'goals' => 'Build scalable systems, Improve team skills'
    ]);
}
```

### Step 3: Verify Data
```php
$user = \App\Models\User::where('email', 'user@example.com')->first();
$userRR = $user->userRR;
if ($userRR) {
    echo "Role: " . $userRR->role . "\n";
    echo "Responsibilities: " . $userRR->responsibilities . "\n";
    echo "Goals: " . $userRR->goals . "\n";
}
```

## Data Format

- **Role**: Single line text (max 255 characters)
- **Responsibilities**: Comma-separated values
  - Example: `"Task 1, Task 2, Task 3"`
- **Goals**: Comma-separated values
  - Example: `"Goal 1, Goal 2, Goal 3"`

## Notes

- Each user can have only **one** R&R record (enforced by unique constraint)
- Using `updateOrCreate()` will create if not exists, or update if exists
- Empty values are allowed (can be null)
- The system automatically converts comma-separated strings to arrays for display
