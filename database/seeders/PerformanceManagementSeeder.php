<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Designation;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;

class PerformanceManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample designations
        $designations = [
            [
                'name' => 'Developer',
                'description' => 'Software Development Team',
                'categories' => [
                    [
                        'name' => 'Technical Skills',
                        'description' => 'Programming and technical competencies',
                        'items' => [
                            ['question' => 'Code Quality: Writes clean, maintainable code', 'weight' => 1.5],
                            ['question' => 'Problem Solving: Effectively troubleshoots and resolves issues', 'weight' => 1.5],
                            ['question' => 'Technical Knowledge: Stays updated with latest technologies', 'weight' => 1.2],
                            ['question' => 'Testing: Writes comprehensive unit tests', 'weight' => 1.0],
                        ]
                    ],
                    [
                        'name' => 'Communication',
                        'description' => 'Team communication and collaboration',
                        'items' => [
                            ['question' => 'Clear Communication: Explains technical concepts clearly', 'weight' => 1.2],
                            ['question' => 'Documentation: Maintains proper code documentation', 'weight' => 1.0],
                            ['question' => 'Team Collaboration: Works well with team members', 'weight' => 1.3],
                        ]
                    ],
                    [
                        'name' => 'Productivity',
                        'description' => 'Work efficiency and output',
                        'items' => [
                            ['question' => 'Task Completion: Meets deadlines consistently', 'weight' => 1.5],
                            ['question' => 'Time Management: Efficiently manages time and priorities', 'weight' => 1.2],
                            ['question' => 'Initiative: Takes proactive approach to work', 'weight' => 1.0],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'SEO Executive',
                'description' => 'Search Engine Optimization',
                'categories' => [
                    [
                        'name' => 'SEO Skills',
                        'description' => 'Search engine optimization expertise',
                        'items' => [
                            ['question' => 'Keyword Research: Identifies relevant keywords effectively', 'weight' => 1.5],
                            ['question' => 'On-Page SEO: Implements on-page optimization correctly', 'weight' => 1.5],
                            ['question' => 'Link Building: Develops quality backlinks', 'weight' => 1.3],
                            ['question' => 'Analytics: Analyzes and reports SEO metrics accurately', 'weight' => 1.2],
                        ]
                    ],
                    [
                        'name' => 'Content Optimization',
                        'description' => 'Content creation and optimization',
                        'items' => [
                            ['question' => 'Content Quality: Creates SEO-friendly, engaging content', 'weight' => 1.5],
                            ['question' => 'Meta Tags: Writes effective meta descriptions and titles', 'weight' => 1.2],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Operations Manager',
                'description' => 'Operations and Process Management',
                'categories' => [
                    [
                        'name' => 'Leadership',
                        'description' => 'Team leadership and management',
                        'items' => [
                            ['question' => 'Team Management: Effectively manages team members', 'weight' => 1.5],
                            ['question' => 'Decision Making: Makes sound operational decisions', 'weight' => 1.5],
                            ['question' => 'Conflict Resolution: Handles team conflicts professionally', 'weight' => 1.2],
                        ]
                    ],
                    [
                        'name' => 'Process Improvement',
                        'description' => 'Operational efficiency',
                        'items' => [
                            ['question' => 'Process Optimization: Identifies and implements process improvements', 'weight' => 1.5],
                            ['question' => 'Efficiency: Improves operational efficiency', 'weight' => 1.3],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Content Manager',
                'description' => 'Content Creation and Management',
                'categories' => [
                    [
                        'name' => 'Content Creation',
                        'description' => 'Content quality and creativity',
                        'items' => [
                            ['question' => 'Content Quality: Creates high-quality, engaging content', 'weight' => 1.5],
                            ['question' => 'Creativity: Brings creative ideas to content', 'weight' => 1.3],
                            ['question' => 'Grammar & Style: Maintains proper grammar and writing style', 'weight' => 1.2],
                        ]
                    ],
                    [
                        'name' => 'Content Strategy',
                        'description' => 'Strategic content planning',
                        'items' => [
                            ['question' => 'Content Planning: Develops effective content strategies', 'weight' => 1.5],
                            ['question' => 'Audience Understanding: Understands target audience needs', 'weight' => 1.3],
                        ]
                    ]
                ]
            ],
        ];

        foreach ($designations as $desData) {
            $designation = Designation::create([
                'name' => $desData['name'],
                'description' => $desData['description'],
                'is_active' => true,
            ]);

            $categoryOrder = 0;
            foreach ($desData['categories'] as $catData) {
                $category = ChecklistCategory::create([
                    'designation_id' => $designation->id,
                    'name' => $catData['name'],
                    'description' => $catData['description'],
                    'order' => $categoryOrder++,
                    'is_active' => true,
                ]);

                $itemOrder = 0;
                foreach ($catData['items'] as $itemData) {
                    ChecklistItem::create([
                        'category_id' => $category->id,
                        'question' => $itemData['question'],
                        'weight' => $itemData['weight'],
                        'order' => $itemOrder++,
                        'is_active' => true,
                    ]);
                }
            }
        }

        $this->command->info('Performance Management sample data seeded successfully!');
    }
}
