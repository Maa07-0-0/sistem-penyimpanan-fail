<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Location;
use App\Models\File;
use App\Models\BorrowingRecord;

class ProductionDataSeeder extends Seeder
{
    /**
     * Run the database seeds for production data migration from MySQL
     */
    public function run(): void
    {
        // Read exported data
        $exportFile = base_path('postgresql-export.json');
        
        if (!file_exists($exportFile)) {
            $this->command->error('PostgreSQL export file not found. Please run mysql-to-postgresql-migrator.php first.');
            return;
        }
        
        $data = json_decode(file_get_contents($exportFile), true);
        
        if (!$data || !isset($data['data'])) {
            $this->command->error('Invalid export data format.');
            return;
        }
        
        $this->command->info('Starting production data migration...');
        
        // Disable foreign key checks temporarily
        DB::statement('SET foreign_key_checks = 0;');
        
        try {
            // Seed users first (due to foreign key dependencies)
            if (isset($data['data']['users'])) {
                $this->seedUsers($data['data']['users']);
            }
            
            // Seed locations
            if (isset($data['data']['locations'])) {
                $this->seedLocations($data['data']['locations']);
            }
            
            // Seed files
            if (isset($data['data']['files'])) {
                $this->seedFiles($data['data']['files']);
            }
            
            // Seed borrowing records
            if (isset($data['data']['borrowing_records'])) {
                $this->seedBorrowingRecords($data['data']['borrowing_records']);
            }
            
        } catch (\Exception $e) {
            $this->command->error('Migration failed: ' . $e->getMessage());
            throw $e;
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET foreign_key_checks = 1;');
        }
        
        $this->command->info('Production data migration completed successfully!');
    }
    
    private function seedUsers($users): void
    {
        $this->command->info('Seeding users...');
        
        foreach ($users as $user) {
            User::create([
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'email_verified_at' => $user['email_verified_at'],
                'password' => $user['password'], // Already hashed in export
                'role' => $user['role'],
                'department' => $user['department'],
                'position' => $user['position'],
                'phone' => $user['phone'],
                'is_active' => (bool) $user['is_active'],
                'remember_token' => $user['remember_token'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ]);
        }
        
        $this->command->info('✓ Seeded ' . count($users) . ' users');
    }
    
    private function seedLocations($locations): void
    {
        $this->command->info('Seeding locations...');
        
        foreach ($locations as $location) {
            Location::create([
                'id' => $location['id'],
                'location_code' => $location['location_code'],
                'name' => $location['name'],
                'building' => $location['building'],
                'floor' => $location['floor'],
                'room' => $location['room'],
                'description' => $location['description'],
                'is_active' => (bool) $location['is_active'],
                'created_at' => $location['created_at'],
                'updated_at' => $location['updated_at'],
            ]);
        }
        
        $this->command->info('✓ Seeded ' . count($locations) . ' locations');
    }
    
    private function seedFiles($files): void
    {
        $this->command->info('Seeding files...');
        
        foreach ($files as $file) {
            File::create([
                'id' => $file['id'],
                'file_id' => $file['file_id'],
                'title' => $file['title'],
                'reference_number' => $file['reference_number'],
                'document_year' => $file['document_year'],
                'department' => $file['department'],
                'document_type' => $file['document_type'],
                'description' => $file['description'],
                'status' => $file['status'],
                'location_id' => $file['location_id'],
                'created_by' => $file['created_by'],
                'updated_by' => $file['updated_by'],
                'created_at' => $file['created_at'],
                'updated_at' => $file['updated_at'],
                'deleted_at' => $file['deleted_at'],
            ]);
        }
        
        $this->command->info('✓ Seeded ' . count($files) . ' files');
    }
    
    private function seedBorrowingRecords($records): void
    {
        $this->command->info('Seeding borrowing records...');
        
        foreach ($records as $record) {
            BorrowingRecord::create([
                'id' => $record['id'],
                'file_id' => $record['file_id'],
                'borrower_id' => $record['borrower_id'],
                'approved_by' => $record['approved_by'],
                'purpose' => $record['purpose'],
                'borrowed_date' => $record['borrowed_date'],
                'due_date' => $record['due_date'],
                'returned_date' => $record['returned_date'],
                'returned_to' => $record['returned_to'],
                'status' => $record['status'],
                'notes' => $record['notes'],
                'created_at' => $record['created_at'],
                'updated_at' => $record['updated_at'],
            ]);
        }
        
        $this->command->info('✓ Seeded ' . count($records) . ' borrowing records');
    }
}