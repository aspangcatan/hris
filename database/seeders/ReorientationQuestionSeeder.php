<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Initial post test questions. Only inserts a question if the module has no
// active questions yet, so HR edits made in-app are never overwritten.
class ReorientationQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            1 => "Explain how CSMC’s Vision, Mission, and Core Values guide employees in performing their duties and delivering quality healthcare services. Provide at least one practical example of how you can demonstrate these values in your workplace.",
            2 => "Discuss the importance of Learning and Development Interventions and the PRAISE system in promoting employee competence, motivation, and excellence in public service. How can these HR systems contribute to your professional growth and the overall performance of CSMC?",
            3 => "As a CSMC employee, how can you contribute to maintaining a safe, professional, and orderly workplace? Discuss at least three relevant policies in Module 3 and explain how you would apply them in your daily work.",
            4 => "Let’s put you in a situation where you need to apply for leave or determine your eligibility for a particular employee benefit. How would you use the guidelines in Module 4 to ensure that you follow the proper procedures?",
        ];

        foreach ($questions as $moduleNo => $question) {
            $exists = DB::table('hris.reorientation_questions')
                ->where('module_no', $moduleNo)
                ->where('is_active', 1)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('hris.reorientation_questions')->insert([
                'module_no'  => $moduleNo,
                'question'   => $question,
                'order_no'   => 1,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
