<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\ProfessionalCategory;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $categories=[
            'Early Childhood Educators','ECE Instructors','Speech-Language Pathologists',
            'Occupational Therapists','Behaviour Consultants','Early Intervention Specialists',
            'Special Education Teachers','Child Psychologists','Childcare Consultants',
            'Curriculum Specialists','Childcare Website Developers','Marketing Professionals',
            'Graphic Designers','Licensing Consultants','Trainers & Workshop Facilitators',
            'Nutritionists','Photographers','Recruiters','Business Coaches','Playground Designers',
            'Interior Designers','Cleaning Companies','Bookkeepers','Grant Writers','Accountants',
            'Legal professionals','Insurance brokers'
        ];
        foreach($categories as $name) ProfessionalCategory::firstOrCreate(
            ['name'=>$name],['slug'=>str()->slug($name),'is_active'=>true]
        );
    }
}
