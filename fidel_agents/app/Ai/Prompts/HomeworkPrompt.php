<?php

namespace App\Ai\Prompts;

class HomeworkPrompt
{
    public static function buildForOllama(string $text, bool $hasImage, string $subjectHint = 'general education', string $gradeHint = 'unknown'): string
    {
        $context = trim($text) !== '' ? trim($text) : 'No text provided.';
        $subjectHint = trim($subjectHint) !== '' ? $subjectHint : 'general education';

        return implode("\n", [
            'Solve this homework problem step by step. Return only valid JSON.',
            '',
            'Problem: ' . $context,
            'Subject: ' . $subjectHint,
            '',
            'Return JSON: {"request_id":"<uuid>","subject":"<subject>","grade_level":"<grade>","problem":"<problem statement>","steps":["step1","step2"],"final_answer":"<answer>","learning_tip":"<tip>"}',
        ]);
    }

    public static function build(string $text, bool $hasImage, string $subjectHint = 'general education', string $gradeHint = 'unknown'): string
    {
        $cleanText = trim($text);
        $context = $cleanText !== '' ? $cleanText : 'No additional student text was provided.';
        $subjectHint = trim($subjectHint) !== '' ? $subjectHint : 'general education';
        $gradeHint = trim($gradeHint) !== '' ? $gradeHint : 'unknown';
        $inputMode = $hasImage
            ? 'An image is attached. You must read the problem from the image and combine it with any student text.'
            : 'No image is attached. You must solve based only on student text.';

        return implode("\n", [
            'TASK',
            'You are an educational homework tutor. Solve the student problem with complete step-by-step teaching.',
            $inputMode,
            '',
            'STUDENT CONTEXT',
            $context,
            'SUBJECT HINT: '.$subjectHint,
            'GRADE LEVEL HINT: '.$gradeHint,
            '',
            'STEPS',
            '1) Identify the exact problem statement from provided inputs.',
            '2) Determine subject and estimated difficulty.',
            '3) Solve the problem with explicit sequential reasoning.',
            '4) Write every intermediate step in order and do not skip steps.',
            '5) Verify internal consistency before giving the final answer.',
            '6) Provide one short learning tip that helps the student improve.',
            '',
            'CONSTRAINTS',
            '- Do not hallucinate facts, values, formulas, or missing data.',
            '- If input is ambiguous, state assumptions clearly inside the explanation steps.',
            '- Keep an educational tone suitable for students.',
            '- Never skip reasoning steps.',
            '- Keep each step concise but complete.',
            '- Output must be compatible with strict structured response parsing.',
            '',
            'OUTPUT FORMAT',
            '- Return only one valid JSON object that matches the structured output schema.',
            '- Do not include markdown, code fences, headings, or extra keys outside the JSON object.',
            '- Required keys: request_id, subject, grade_level, problem, steps, final_answer, learning_tip.',
            '- Optional keys: ocr_confidence, llm_confidence, processed_offline, ocr_provider, ocr_model, llm_provider, llm_model.',
            '',
            'EXAMPLE OUTPUT',
            '{',
            '  "request_id": "<uuid>",',
            '  "subject": "Mathematics",',
            '  "grade_level": "10th grade",',
            '  "problem": "Find the slope of the line passing through points (1, 2) and (3, 8).",',
            '  "steps": [',
            '    "Identify the two points on the line.",',
            '    "Use the slope formula: (y2 - y1) / (x2 - x1).",',
            '    "Compute the difference in y and difference in x.",',
            '    "Divide and simplify to get the slope."',
            '  ],',
            '  "final_answer": "The slope is 3.",',
            '  "learning_tip": "Remember that slope measures rise over run.",',
            '  "ocr_confidence": 0.0,',
            '  "llm_confidence": 0.0,',
            '  "processed_offline": true',
            '}',
            '',
            'QUALITY CHECK',
            'Before finalizing, ensure the steps logically lead to final_answer and contain no contradictions.',
        ]);
    }
}
